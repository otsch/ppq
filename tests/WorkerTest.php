<?php

use Integration\WorkerProcess;
use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Utils;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    WorkerProcess::work('WorkerTest');
});

afterAll(function () {
    WorkerProcess::stop();
});

it('processes queue jobs', function () {
    expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    $finishedJob = Utils::tryUntil(function () use ($job) {
        $updatedJob = Config::getDriver()->get($job->id);

        if (!$updatedJob) {
            throw new Exception('Job disappeared');
        }

        return $updatedJob->status === QueueJobStatus::finished ? $updatedJob : false;
    }, sleep: 30000);

    expect($finishedJob)->toBeInstanceOf(QueueRecord::class);

    expect($finishedJob->status)->toBe(QueueJobStatus::finished);
});

it('removes done (finished, failed, lost) jobs that exceed the configured limit of jobs to keep', function () {
    expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeTrue();

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

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::lost));

    Config::getDriver()->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    $time = \Otsch\Ppq\WorkerProcess::getTime();

    Utils::tryUntil(function () use ($time) {
        return \Otsch\Ppq\WorkerProcess::getTime() !== $time;
    }, sleep: 50000);

    $doneJobsCount = 0;

    foreach (Ppq::where('default') as $job) {
        if ($job->status->isPast()) {
            $doneJobsCount++;
        }
    }

    expect($doneJobsCount)->toBe(10);
});

it('stops working the queues when it receives the stop signal', function () {
    expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeTrue();

    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    Utils::tryUntil(function () use ($job) {
        return Config::getDriver()->get($job->id)?->status !== QueueJobStatus::waiting;
    });

    Kernel::ppqCommand('stop')->run();

    Utils::tryUntil(function () {
        return \Otsch\Ppq\WorkerProcess::isWorking() === false;
    });

    expect(Config::getDriver()->get($job->id)?->status)->toBe(QueueJobStatus::finished);

    expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeFalse();
});
