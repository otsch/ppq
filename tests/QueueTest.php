<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Process;
use Otsch\Ppq\Queue;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    if (!file_exists(__DIR__ . '/_testdata/datapath/index')) {
        touch(__DIR__ . '/_testdata/datapath/index');
    }

    file_put_contents(__DIR__ . '/_testdata/datapath/index', 'a:0:{}');

    if (!file_exists(__DIR__ . '/_testdata/datapath/queue-default')) {
        touch(__DIR__ . '/_testdata/datapath/queue-default');
    }

    file_put_contents(__DIR__ . '/_testdata/datapath/queue-default', 'a:0:{}');
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

    Config::getDriver()->add($job);

    $queue = new Queue('default', 2, 10);

    $queue->startWaitingJob($job);

    expect($job->status)->toBe(QueueJobStatus::running);

    expect($job->pid)->toBeInt()->toBeGreaterThan(0);

    $pid = $job->pid;

    /** @var int $pid */

    $tries = 0;

    while (Process::runningPhpProcessWithPidExists($pid) && $tries <= 100) {
        usleep(10000);

        $tries++;
    }

    $queue->hasAvailableSlot();

    expect($job->status)->toBe(QueueJobStatus::finished);
});

it('knows if there is a slot available for another job', function () {
    $queue = new Queue('default', 2, 10);

    expect($queue->hasAvailableSlot())->toBeTrue();

    $jobOne = helper_addQueueJob();

    $queue->startWaitingJob($jobOne);

    expect($queue->hasAvailableSlot())->toBeTrue();

    $jobTwo = helper_addQueueJob();

    $queue->startWaitingJob($jobTwo);

    expect($queue->hasAvailableSlot())->toBeFalse();

    usleep(200000);

    expect($queue->hasAvailableSlot())->toBeTrue();

    expect($jobOne->status)->toBe(QueueJobStatus::finished);

    expect($jobTwo->status)->toBe(QueueJobStatus::finished);
});

it('clears forgotten jobs with status running', function () {
    $queue = new Queue('default', 2, 10);

    $runningJob = helper_addQueueJob(job: new QueueRecord('default', TestJob::class, QueueJobStatus::running));

    expect(Config::getDriver()->where('default', status: QueueJobStatus::running))->toHaveCount(1);

    $queue->clearRunningJobs();

    expect(Config::getDriver()->where('default', status: QueueJobStatus::running))->toBeEmpty();

    $clearedJob = Config::getDriver()->get($runningJob->id);

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

    usleep(200000);

    expect($queue->runningProcessesCount())->toBe(0);
});
