<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
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

function helper_addQueueJob(string $queue = 'default'): QueueRecord
{
    $job = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($job);

    return $job;
}

it('starts a waiting job', function () {
    $job = helper_addQueueJob();

    Config::getDriver()->add($job);

    $queue = new Queue('default', 2, 10);

    $queue->startWaitingJob($job);

    expect($job->status)->toBe(QueueJobStatus::running);

    expect($job->pid)->not()->toBeNull();

    usleep(50000);

    $queue->hasAvailableSlot();

    expect($job->status)->toBe(QueueJobStatus::finished);
});

it('handles concurrently running jobs', function () {
    $queue = new Queue('default', 2, 10);

    expect($queue->hasAvailableSlot())->toBeTrue();

    $jobOne = helper_addQueueJob();

    $queue->startWaitingJob($jobOne);

    expect($queue->hasAvailableSlot())->toBeTrue();

    $jobTwo = helper_addQueueJob();

    $queue->startWaitingJob($jobTwo);

    expect($queue->hasAvailableSlot())->toBeFalse();

    usleep(100000);

    expect($queue->hasAvailableSlot())->toBeTrue();

    expect($jobOne->status)->toBe(QueueJobStatus::finished);

    expect($jobTwo->status)->toBe(QueueJobStatus::finished);
});
