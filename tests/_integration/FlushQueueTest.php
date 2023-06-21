<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Ppq;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::stop();
});

afterAll(function () {
    helper_cleanUpDataPathQueueFiles();
});

function helper_createQueueJobsWithRandomStatuses(string $queue, int $number): void
{
    $queueJobStatuses = QueueJobStatus::cases();

    for ($i = 1; $i <= $number; $i++) {
        $status = $queueJobStatuses[rand(0, 5)];

        Config::getDriver()->add(new QueueRecord($queue, TestJob::class, $status));
    }
}

it('flushes a queue', function () {
    helper_createQueueJobsWithRandomStatuses('infinite_waiting_jobs_queue', 20);

    expect(Ppq::where('infinite_waiting_jobs_queue'))->toHaveCount(20);

    $flushCommand = Kernel::ppqCommand('flush infinite_waiting_jobs_queue');

    $flushCommand->run();

    expect($flushCommand->isSuccessful())->toBeTrue();

    expect(Ppq::where('infinite_waiting_jobs_queue'))->toHaveCount(0);

    expect($flushCommand->getOutput())->toContain('Flushed queue infinite_waiting_jobs_queue');
});

it('flushes all queues', function () {
    helper_createQueueJobsWithRandomStatuses('default', 10);

    helper_createQueueJobsWithRandomStatuses('other_queue', 10);

    helper_createQueueJobsWithRandomStatuses('infinite_waiting_jobs_queue', 10);

    expect(Ppq::where('default'))->toHaveCount(10);

    expect(Ppq::where('other_queue'))->toHaveCount(10);

    expect(Ppq::where('infinite_waiting_jobs_queue'))->toHaveCount(10);

    $flushCommand = Kernel::ppqCommand('flush-all');

    $flushCommand->run();

    expect($flushCommand->isSuccessful())->toBeTrue();

    expect(Ppq::where('default'))->toHaveCount(0);

    expect(Ppq::where('other_queue'))->toHaveCount(0);

    expect(Ppq::where('infinite_waiting_jobs_queue'))->toHaveCount(0);

    expect($flushCommand->getOutput())->toContain('Flushed all queues');
});
