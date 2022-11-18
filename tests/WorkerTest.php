<?php

use Integration\WorkerProcess;
use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Process;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    WorkerProcess::work();
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();
});

afterAll(function () {
    WorkerProcess::stop();
});

it('processes queue jobs', function () {
    expect(Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    $finishedJob = helper_tryUntil(function () use ($job) {
        $updatedJob = Config::getDriver()->get($job->id);

        if (!$updatedJob) {
            throw new Exception('Job disappeared');
        }

        return $updatedJob->status === QueueJobStatus::finished ? $updatedJob : false;
    });

    expect($finishedJob)->toBeInstanceOf(QueueRecord::class);

    expect($finishedJob->status)->toBe(QueueJobStatus::finished);
});

it('removes done (finished, failed, lost) jobs that exceed the configured limit of jobs to keep', function () {
    expect(Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::failed));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::failed));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::lost));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    usleep(200000);

    $doneJobsCount = 0;

    foreach (Ppq::where('default') as $job) {
        if ($job->status->isPast()) {
            $doneJobsCount++;
        }
    }

    expect($doneJobsCount)->toBe(10);
});

it('stops working the queues when it receives the stop signal', function () {
    expect(Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    helper_tryUntil(function () use ($job) {
        return Config::getDriver()->get($job->id)?->status !== QueueJobStatus::waiting;
    });

    Kernel::ppqCommand('stop')->run();

    helper_tryUntil(function () {
        return Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']) === false;
    });

    expect(Config::getDriver()->get($job->id)?->status)->toBe(QueueJobStatus::finished);

    expect(Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeFalse();
});
