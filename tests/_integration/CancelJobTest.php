<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Ppq;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    WorkerProcess::work();
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();
});

afterAll(function () {
    WorkerProcess::stop();
});

it('cancels a waiting job', function () {
    $job = Dispatcher::queue('infinite_waiting_jobs_queue')
        ->job(TestJob::class)
        ->dispatch();

    $cancelCommand = Kernel::ppqCommand('cancel ' . $job->id);

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    $updatedJob = Ppq::find($job->id);

    expect($updatedJob?->status)->toBe(QueueJobStatus::cancelled);

    expect($updatedJob?->pid)->toBeNull();
});

it('cancels a running job', function () {
    $job = Dispatcher::queue('default')
        ->job(TestJob::class)
        ->args(['countTo' => 99999999])
        ->dispatch();

    $job = helper_tryUntil(function () use ($job) {
        $job = Ppq::find($job->id);

        return $job?->status === QueueJobStatus::running ? $job : false;
    });

    expect($job)->toBeInstanceOf(QueueRecord::class);

    expect($job->status)->toBe(QueueJobStatus::running);

    $cancelCommand = Kernel::ppqCommand('cancel ' . $job->id);

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    expect(Ppq::find($job->id)?->status)->toBe(QueueJobStatus::cancelled);

    $job = helper_tryUntil(function () use ($job) {
        $job = Ppq::find($job->id);

        return $job?->pid === null ? $job : false;
    });

    $workerProcessOutput = WorkerProcess::$process?->getOutput() ?? '';

    expect(helper_containsInOneLine($workerProcessOutput, ['Started job', $job->id]))->toBeTrue();

    expect(helper_containsInOneLine($workerProcessOutput, ['Cancelled running job', $job->id]))->toBeTrue();
});
