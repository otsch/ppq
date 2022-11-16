<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Stubs\TestJob;
use Symfony\Component\Process\Process;

class WorkerProcess
{
    public static ?Process $process = null;
}

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::$process = Kernel::ppqCommand('work');

    WorkerProcess::$process->start();
});

afterAll(function () {
    if (WorkerProcess::$process?->isRunning()) {
        WorkerProcess::$process->stop();
    }
});

it('processes queue jobs', function () {
    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    $finishedJob = helper_tryUntil(function () use ($job) {
        $updatedJob = Config::getDriver()->get($job->id);

        if (!$updatedJob) {
            throw new Exception('Job disappeared');
        }

        return $updatedJob->status === QueueJobStatus::finished ? $updatedJob : false;
    });

    expect($finishedJob->status)->toBe(QueueJobStatus::finished); // @phpstan-ignore-line
});

it('removes done (finished, failed, lost) jobs that exceed the configured limit of jobs to keep', function () {
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

    foreach (Config::getDriver()->where('default', status: null) as $job) {
        if ($job->status->isPast()) {
            $doneJobsCount++;
        }
    }

    expect($doneJobsCount)->toBe(10);
});

it('stops working the queues when it receives the stop signal', function () {
    expect(\Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    helper_tryUntil(function () use ($job) {
        return Config::getDriver()->get($job->id)?->status !== QueueJobStatus::waiting;
    });

    Kernel::ppqCommand('stop')->run();

    helper_tryUntil(function () {
        return \Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']) === false;
    });

    expect(Config::getDriver()->get($job->id)?->status)->toBe(QueueJobStatus::finished);

    expect(\Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeFalse();
});
