<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Stubs\OtherTestJob;
use Stubs\TestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/ppq.php');

    helper_cleanUpDataPathQueueFiles();
});

it('adds a job to a queue', function () {
    $driver = new FileDriver();

    $job = new QueueRecord('default', TestJob::class);

    $driver->add($job);

    expect($driver->where('default', status: null))->toHaveCount(1);

    expect($driver->get($job->id))->toBeInstanceOf(QueueRecord::class);
});

it('updates a job in the queue', function () {
    $driver = new FileDriver();

    $job = new QueueRecord('other_queue', TestJob::class, QueueJobStatus::running, ['f' => 'b', 'o' => 'a'], 123);

    $driver->add($job);

    expect($driver->where('other_queue', status: null))->toHaveCount(1);

    $addedJob = $driver->get($job->id);

    expect($addedJob)->toBeInstanceOf(QueueRecord::class);

    /** @var QueueRecord $addedJob */

    expect($addedJob->queue)->toBe('other_queue');

    expect($addedJob->jobClass)->toBe(TestJob::class);

    expect($addedJob->status)->toBe(QueueJobStatus::running);

    expect($addedJob->args)->toBe(['f' => 'b', 'o' => 'a']);

    expect($addedJob->pid)->toBe(123);

    expect($addedJob->id)->toBe($job->id);

    $addedJob->status = QueueJobStatus::finished;

    $addedJob->pid = null;

    $driver->update($addedJob);

    $updatedJob = $driver->get($addedJob->id);

    /** @var QueueRecord $updatedJob */

    expect($updatedJob->queue)->toBe('other_queue');

    expect($updatedJob->jobClass)->toBe(TestJob::class);

    expect($updatedJob->status)->toBe(QueueJobStatus::finished);

    expect($updatedJob->args)->toBe(['f' => 'b', 'o' => 'a']);

    expect($updatedJob->pid)->toBeNull();

    expect($updatedJob->id)->toBe($job->id);
});

it('forgets a job', function () {
    $driver = new FileDriver();

    $job = new QueueRecord('default', TestJob::class);

    $driver->add($job);

    expect($driver->where('default', status: null))->toHaveCount(1);

    expect($driver->get($job->id))->toBeInstanceOf(QueueRecord::class);

    $driver->forget($job->id);

    expect($driver->where('default', status: null))->toHaveCount(0);

    expect($driver->get($job->id))->toBeNull();
});

it('finds all jobs from a queue with a certain job class', function () {
    $driver = new FileDriver();

    expect($driver->where('other_queue', OtherTestJob::class, status: null))->toHaveCount(0);

    $driver->add(new QueueRecord('other_queue', OtherTestJob::class));

    expect($driver->where('other_queue', OtherTestJob::class, status: null))->toHaveCount(1);

    $driver->add(new QueueRecord('default', OtherTestJob::class));

    expect($driver->where('other_queue', OtherTestJob::class, status: null))->toHaveCount(1);

    $driver->add(new QueueRecord('other_queue', TestJob::class));

    expect($driver->where('other_queue', OtherTestJob::class, status: null))->toHaveCount(1);

    $driver->add(new QueueRecord('other_queue', OtherTestJob::class, QueueJobStatus::lost));

    expect($driver->where('other_queue', OtherTestJob::class, status: null))->toHaveCount(2);
});

it('finds all jobs from a queue with a certain status', function () {
    $driver = new FileDriver();

    expect($driver->where('default', status: QueueJobStatus::finished))->toHaveCount(0);

    $driver->add(new QueueRecord('default', TestJob::class));

    expect($driver->where('default', status: QueueJobStatus::finished))->toHaveCount(0);

    $driver->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished));

    expect($driver->where('default', status: QueueJobStatus::finished))->toHaveCount(1);

    $driver->add(new QueueRecord('default', TestJob::class, QueueJobStatus::lost));

    expect($driver->where('default', status: QueueJobStatus::finished))->toHaveCount(1);

    $driver->add(new QueueRecord('default', OtherTestJob::class, QueueJobStatus::finished));

    $driver->add(new QueueRecord('default', OtherTestJob::class, QueueJobStatus::finished));

    $driver->add(new QueueRecord('default', OtherTestJob::class, QueueJobStatus::finished));

    expect($driver->where('default', status: QueueJobStatus::finished))->toHaveCount(4);
});

it('finds all jobs from a queue with certain arguments', function () {
    $driver = new FileDriver();

    expect($driver->where('default', status: null, args: ['hello' => 'world']))->toHaveCount(0);

    $driver->add(new QueueRecord('default', TestJob::class, args: ['hello' => 'world']));

    expect($driver->where('default', status: null, args: ['hello' => 'world']))->toHaveCount(1);

    $driver->add(new QueueRecord('default', TestJob::class, args: ['hello' => 'world', 'foo' => 'bar']));

    expect($driver->where('default', status: null, args: ['hello' => 'world']))->toHaveCount(2);

    $driver->add(new QueueRecord('default', TestJob::class, args: ['foo' => 'bar', 'hello' => 'world']));

    expect($driver->where('default', status: null, args: ['hello' => 'world']))->toHaveCount(3);

    $driver->add(new QueueRecord('default', TestJob::class, args: ['foo' => 'bar']));

    expect($driver->where('default', status: null, args: ['hello' => 'world']))->toHaveCount(3);
});

it('finds all jobs from a queue with a certain pid', function () {
    $driver = new FileDriver();

    expect($driver->where('default', status: null, pid: 123))->toHaveCount(0);

    $driver->add(new QueueRecord('default', TestJob::class, pid: 123));

    expect($driver->where('default', status: null, pid: 123))->toHaveCount(1);

    $driver->add(new QueueRecord('default', TestJob::class, pid: 1234));

    expect($driver->where('default', status: null, pid: 123))->toHaveCount(1);

    $driver->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished, pid: 123));

    expect($driver->where('default', status: null, pid: 123))->toHaveCount(2);
});

it('clears a queue', function () {
    $driver = new FileDriver();

    $driver->add(new QueueRecord('other_queue', TestJob::class));

    $driver->add(new QueueRecord('other_queue', TestJob::class));

    $driver->add(new QueueRecord('other_queue', TestJob::class));

    expect($driver->where('other_queue', status: QueueJobStatus::waiting))->toHaveCount(3);

    $driver->clear('other_queue');

    expect($driver->where('other_queue', status: QueueJobStatus::waiting))->toHaveCount(0);
});
