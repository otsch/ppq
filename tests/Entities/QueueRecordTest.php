<?php

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Stubs\OtherTestJob;
use Stubs\TestJob;

it('can be created from an array', function () {
    $queueRecord = QueueRecord::fromArray([
        'queue' => 'some-queue',
        'jobClass' => OtherTestJob::class,
        'status' => QueueJobStatus::running,
        'args' => ['foo' => 'bar', 'lorem' => 'ipsum'],
        'pid' => 12345,
        'id' => '123.abc345',
    ]);

    expect($queueRecord->queue)->toBe('some-queue');

    expect($queueRecord->jobClass)->toBe(OtherTestJob::class);

    expect($queueRecord->status)->toBe(QueueJobStatus::running);

    expect($queueRecord->args)->toBe(['foo' => 'bar', 'lorem' => 'ipsum']);

    expect($queueRecord->pid)->toBe(12345);

    expect($queueRecord->id)->toBe('123.abc345');
});

test('when creating from array status can be provided as string', function () {
    $queueRecord = QueueRecord::fromArray([
        'queue' => 'some-queue',
        'jobClass' => TestJob::class,
        'status' => 'lost',
    ]);

    expect($queueRecord->queue)->toBe('some-queue');

    expect($queueRecord->jobClass)->toBe(TestJob::class);

    expect($queueRecord->status)->toBe(QueueJobStatus::lost);

    expect($queueRecord->args)->toBe([]);

    expect($queueRecord->pid)->toBeNull();

    expect($queueRecord->id)->toBeString()->not()->toBeEmpty();
});

it('throws an exception when no queue name in queue record data, when creating from array', function () {
    QueueRecord::fromArray([
        'jobClass' => TestJob::class,
        'status' => 'waiting',
    ]);
})->throws(Exception::class);

it('throws an exception when no jobClass in queue record data, when creating from array', function () {
    QueueRecord::fromArray([
        'queue' => 'some-queue',
        'status' => 'finished',
    ]);
})->throws(Exception::class);

it('creates an id when not provided', function () {
    expect((new QueueRecord('default', TestJob::class))->id)->toBeString()->not()->toBeEmpty();
});
