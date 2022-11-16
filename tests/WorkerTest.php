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

    public static function work(): void
    {
        if (self::$process && !self::$process->isRunning()) {
            self::stop();
        }

        if (!self::$process) {
            self::$process = Kernel::ppqCommand('work');

            self::$process->start();

            usleep(50000);

            if (!self::$process->isRunning()) {
                if (self::$process->isSuccessful()) {
                    throw new Exception(
                        'Looks like worker process immediately stopped. Output: ' . self::$process->getOutput()
                    );
                } else {
                    throw new Exception(
                        'Looks like worker process immediately died. Error output: ' . self::$process->getErrorOutput()
                    );
                }
            }
        }
    }

    public static function stop(): void
    {
        if (self::$process) {
            self::$process->stop();

            self::$process = null;
        }
    }
}

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
    expect(\Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    $finishedJob = helper_tryUntil(function () use ($job) {
        $updatedJob = Config::getDriver()->get($job->id);

        if (!$updatedJob) {
            throw new Exception('Job disappeared');
        }

        return $updatedJob->status === QueueJobStatus::finished ? $updatedJob : false;
    });

    if (!$finishedJob instanceof QueueRecord) {
        var_dump(WorkerProcess::$process?->getOutput());
    }

    expect($finishedJob)->toBeInstanceOf(QueueRecord::class);

    expect($finishedJob->status)->toBe(QueueJobStatus::finished);
});

it('removes done (finished, failed, lost) jobs that exceed the configured limit of jobs to keep', function () {
    expect(\Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeTrue();

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

    var_dump(Config::getDriver()->get($job->id)?->status);

    expect(Config::getDriver()->get($job->id)?->status)->toBe(QueueJobStatus::finished);

    expect(\Otsch\Ppq\Process::runningPhpProcessContainingStringsExists([Kernel::ppqPath(), 'work']))->toBeFalse();
});
