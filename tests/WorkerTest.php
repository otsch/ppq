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
    Config::setPath(helper_testConfigPath('filesystem-ppq.php'));

    helper_cleanUpDataPathQueueFiles();
});

beforeAll(function () {
    Config::setPath(helper_testConfigPath('filesystem-ppq.php'));

    WorkerProcess::work();
});

afterAll(function () {
    WorkerProcess::stop();
});

function helper_getPastQueueRecordWithDoneTime(int $doneTime): QueueRecord
{
    $statuses = [QueueJobStatus::finished, QueueJobStatus::failed, QueueJobStatus::lost];

    $status = $statuses[rand(0, 2)];

    return new QueueRecord('default', TestJob::class, $status, doneTime: $doneTime);
}

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

it(
    'removes done (finished, failed, lost) jobs that exceed the configured limit of jobs to keep and removes jobs ' .
    'with lower doneTime first',
    function () {
        expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeTrue();

        $now = Utils::currentMicrosecondsInt();

        $addToDoneTime = [3, 4, 6, 1, 7, 5, 2, 9, 11, 8, 10, 12, 14, 13];

        foreach ($addToDoneTime as $addMicroseconds) {
            Config::getDriver()->add(helper_getPastQueueRecordWithDoneTime($now + $addMicroseconds));
        }

        $time = \Otsch\Ppq\WorkerProcess::getTime();

        Utils::tryUntil(function () use ($time) {
            return \Otsch\Ppq\WorkerProcess::getTime() !== $time;
        }, sleep: 50000);

        $doneJobsCount = 0;

        $oldestDoneTime = null;

        foreach (Ppq::where('default') as $job) {
            if ($job->status->isPast()) {
                $doneJobsCount++;
            }

            if (!$oldestDoneTime || $job->doneTime < $oldestDoneTime) {
                $oldestDoneTime = $job->doneTime;
            }
        }

        expect($doneJobsCount)->toBe(10);

        expect($oldestDoneTime)->toBe($now + 5);
    }
);

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
