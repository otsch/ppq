<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Queue;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');
});

it('starts a waiting job', function () {
    $waitingJob = new QueueRecord('default', TestJob::class);

    $queue = new Queue('default', 2, 10);

    $queue->startWaitingJob($waitingJob);

    expect($waitingJob->status)->toBe(QueueJobStatus::running);

    expect($waitingJob->pid)->not()->toBeNull();

    usleep(20000);

    $queue->hasAvailableSlot();
});

it('handles concurrently running jobs', function () {
    $queue = new Queue('default', 2, 10);

    expect($queue->hasAvailableSlot())->toBeTrue();

    $queue->startWaitingJob(new QueueRecord('default', TestJob::class));

    expect($queue->hasAvailableSlot())->toBeTrue();

    $queue->startWaitingJob(new QueueRecord('default', TestJob::class));

    expect($queue->hasAvailableSlot())->toBeFalse();

    usleep(60000);

    expect($queue->hasAvailableSlot())->toBeTrue();
});
