<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Process;
use Otsch\Ppq\Queue;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();
});

function helper_addQueueJob(string $queue = 'default', ?QueueRecord $job = null): QueueRecord
{
    if (!$job) {
        $job = new QueueRecord('default', TestJob::class);
    }

    Config::getDriver()->add($job);

    return $job;
}

it('starts a waiting job', function () {
    $job = helper_addQueueJob();

    $queue = new Queue('default', 2, 10);

    $queue->startWaitingJob($job);

    expect($job->status)->toBe(QueueJobStatus::running);

    expect($job->pid)->toBeInt()->toBeGreaterThan(0);

    $pid = $job->pid;

    /** @var int $pid */

    helper_tryUntil(function () use ($pid) {
        return !Process::runningPhpProcessWithPidExists($pid);
    });

    expect($queue->hasAvailableSlot())->toBeTrue();

    expect(Ppq::find($job->id)?->status)->toBe(QueueJobStatus::finished);
});

it('knows if there is a slot available for another job', function () {
    $queue = new Queue('default', 2, 10);

    expect($queue->hasAvailableSlot())->toBeTrue();

    expect($queue->runningProcessesCount())->toBe(0);

    $jobOne = helper_addQueueJob();

    $queue->startWaitingJob($jobOne);

    expect($queue->hasAvailableSlot())->toBeTrue();

    expect($queue->runningProcessesCount())->toBe(1);

    $jobTwo = helper_addQueueJob();

    $queue->startWaitingJob($jobTwo);

    expect($queue->hasAvailableSlot())->toBeFalse();

    helper_tryUntil(function () use ($queue) {
        return $queue->runningProcessesCount() < 2;
    });

    expect($queue->hasAvailableSlot())->toBeTrue();

    helper_tryUntil(function () use ($queue) {
        return $queue->runningProcessesCount() === 0;
    });

    expect(Ppq::find($jobOne->id)?->status)->toBe(QueueJobStatus::finished);

    expect(Ppq::find($jobTwo->id)?->status)->toBe(QueueJobStatus::finished);
});

it('clears forgotten jobs with status running', function () {
    $queue = new Queue('default', 2, 10);

    $runningJob = helper_addQueueJob(job: new QueueRecord('default', TestJob::class, QueueJobStatus::running));

    expect(Ppq::running('default'))->toHaveCount(1);

    $queue->clearRunningJobs();

    expect(Ppq::running('default'))->toBeEmpty();

    $clearedJob = Ppq::find($runningJob->id);

    expect($clearedJob->status)->toBe(QueueJobStatus::lost); // @phpstan-ignore-line
});

it('tells you how many processes are currently running', function () {
    $queue = new Queue('default', 2, 10);

    // Fake running job that should be identified when checking the runningProcessesCount
    helper_addQueueJob(job: new QueueRecord('default', TestJob::class, QueueJobStatus::running));

    expect($queue->runningProcessesCount())->toBe(0);

    $queue->startWaitingJob(helper_addQueueJob());

    expect($queue->runningProcessesCount())->toBe(1);

    $queue->startWaitingJob(helper_addQueueJob());

    expect($queue->runningProcessesCount())->toBe(2);

    expect($queue->hasAvailableSlot())->toBeFalse();

    $queue->startWaitingJob(helper_addQueueJob());

    // It can start more than the defined concurrency limit.
    // The startWaitingJob() method is not responsible for obeying the limit.
    expect($queue->runningProcessesCount())->toBe(3);

    helper_tryUntil(function () use ($queue) {
        return $queue->runningProcessesCount() === 0;
    });

    expect($queue->runningProcessesCount())->toBe(0);
});
