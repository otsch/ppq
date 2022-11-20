<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
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

function helper_addJobsToQueue(string $queue, int $number): void
{
    for ($i = 1; $i <= $number; $i++) {
        Dispatcher::queue($queue)
            ->job(TestJob::class)
            ->args(['countTo' => 999999999])
            ->dispatch();
    }
}

it('clears a queue', function () {
    helper_addJobsToQueue('infinite_waiting_jobs_queue', 5);

    expect(Ppq::waiting('infinite_waiting_jobs_queue'))->toHaveCount(5);

    $cancelCommand = Kernel::ppqCommand('clear infinite_waiting_jobs_queue');

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    expect(Ppq::waiting('infinite_waiting_jobs_queue'))->toHaveCount(0);

    expect($cancelCommand->getOutput())->toContain('Cleared queue infinite_waiting_jobs_queue');
});

it('clears all queues', function () {
    helper_addJobsToQueue('default', 5);

    helper_addJobsToQueue('other_queue', 5);

    helper_addJobsToQueue('infinite_waiting_jobs_queue', 5);

    expect(Ppq::waiting('default'))->toHaveCount(5);

    expect(Ppq::waiting('other_queue'))->toHaveCount(5);

    expect(Ppq::waiting('infinite_waiting_jobs_queue'))->toHaveCount(5);

    $cancelCommand = Kernel::ppqCommand('clear-all');

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    expect(Ppq::waiting('default'))->toHaveCount(0);

    expect(Ppq::waiting('other_queue'))->toHaveCount(0);

    expect(Ppq::waiting('infinite_waiting_jobs_queue'))->toHaveCount(0);

    expect($cancelCommand->getOutput())->toContain('Cleared all queues');
});
