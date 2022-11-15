<?php

use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Stubs\SimpleInMemoryDriver;
use Stubs\TestJob;

function helper_getDispatcher(string $queueName = 'default', ?QueueDriver $driver = null): Dispatcher
{
    if (!$driver) {
        $driver = new SimpleInMemoryDriver();
    }

    return new Dispatcher($queueName, $driver);
}

it('throws an error if you don\'t define any job class', function () {
    helper_getDispatcher()->dispatch();
})->throws(Exception::class);

it('calls the add method of the QueueDriver when a job is dispatched', function () {
    $driver = new SimpleInMemoryDriver();

    helper_getDispatcher(driver: $driver)
        ->job(TestJob::class)
        ->dispatch();

    $queueRecord = reset($driver->queue['default']);

    expect(reset($driver->queue['default']))->toBeInstanceOf(QueueRecord::class);

    /** @var QueueRecord $queueRecord */

    expect($queueRecord->jobClass)->toBe(TestJob::class);

    expect($queueRecord->queue)->toBe('default');

    expect($queueRecord->args)->toBe([]);
});

it('adds arguments to the added QueueRecord when calling the args method', function () {
    $driver = new SimpleInMemoryDriver();

    helper_getDispatcher(driver: $driver)
        ->job(TestJob::class)
        ->args(['foo' => 'one', 'bar' => 2])
        ->dispatch();

    $queueRecord = reset($driver->queue['default']);

    /** @var QueueRecord $queueRecord */

    expect($queueRecord->args)->toBe(['foo' => 'one', 'bar' => 2]);
});

it('sets the queue property of the QueueRecord instance when dispatching a job to queue', function () {
    $driver = new SimpleInMemoryDriver();

    helper_getDispatcher('special-queue', $driver)
        ->job(TestJob::class)
        ->dispatch();

    $queueRecord = reset($driver->queue['special-queue']);

    /** @var QueueRecord $queueRecord */

    expect($queueRecord->queue)->toBe('special-queue');
});

it(
    'adds a job using the dispatchIfNotYetInQueue() method when no Job of the same class is in the queue yet',
    function () {
        $driver = new SimpleInMemoryDriver();

        helper_getDispatcher(driver: $driver)
            ->job(TestJob::class)
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(1);
    }
);

it(
    'doesn\'t add a job using the dispatchIfNotYetInQueue() method when a Job of the same class is already in queue ' .
    'with status waiting',
    function () {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->dispatch();

        $dispatcher
            ->job(TestJob::class)
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(1);
    }
);

it(
    'doesn\'t add a job using the dispatchIfNotYetInQueue() method when a Job of the same class is already in queue ' .
    'with status running',
    function () {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->dispatch();

        expect($driver->queue['default'])->toHaveCount(1);

        $jobInQueue = reset($driver->queue['default']);

        /** @var QueueRecord $jobInQueue */

        $jobInQueue->status = QueueJobStatus::running;

        $driver->update($jobInQueue);

        $dispatcher
            ->job(TestJob::class)
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(1);
    }
);

it(
    'adds a job using the dispatchIfNotYetInQueue() method when a Job of the same class is already in queue with ' .
    'other statuses',
    function (QueueJobStatus $status) {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->dispatch();

        expect($driver->queue['default'])->toHaveCount(1);

        $jobInQueue = reset($driver->queue['default']);

        /** @var QueueRecord $jobInQueue */

        $jobInQueue->status = $status;

        $driver->update($jobInQueue);

        $dispatcher
            ->job(TestJob::class)
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(2);
    }
)->with([QueueJobStatus::failed, QueueJobStatus::finished, QueueJobStatus::lost]);

it(
    'doesn\'t add a job using the dispatchIfNotYetInQueue() method when a Job of the same class, with the same ' .
    'arguments is already in queue with status waiting',
    function () {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatch();

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(1);
    }
);

it(
    'doesn\'t add a job using the dispatchIfNotYetInQueue() method when a Job of the same class, with the same ' .
    'arguments is already in queue with status running',
    function () {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatch();

        expect($driver->queue['default'])->toHaveCount(1);

        $jobInQueue = reset($driver->queue['default']);

        /** @var QueueRecord $jobInQueue */

        $jobInQueue->status = QueueJobStatus::running;

        $driver->update($jobInQueue);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(1);
    }
);

it(
    'adds a job using the dispatchIfNotYetInQueue() method when a Job of the same class, with the same arguments is ' .
    'already in queue with other statuses',
    function (QueueJobStatus $status) {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatch();

        expect($driver->queue['default'])->toHaveCount(1);

        $jobInQueue = reset($driver->queue['default']);

        /** @var QueueRecord $jobInQueue */

        $jobInQueue->status = $status;

        $driver->update($jobInQueue);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(2);
    }
)->with([QueueJobStatus::failed, QueueJobStatus::finished, QueueJobStatus::lost]);

it(
    'adds a job using the dispatchIfNotYetInQueue() method when a Job of the same class, with different arguments is ' .
    'already in queue with any status',
    function (QueueJobStatus $status) {
        $driver = new SimpleInMemoryDriver();

        $dispatcher = helper_getDispatcher(driver: $driver);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'bar', 'yo' => 'lo'])
            ->dispatch();

        expect($driver->queue['default'])->toHaveCount(1);

        $jobInQueue = reset($driver->queue['default']);

        /** @var QueueRecord $jobInQueue */

        $jobInQueue->status = $status;

        $driver->update($jobInQueue);

        $dispatcher
            ->job(TestJob::class)
            ->args(['foo' => 'lo', 'yo' => 'bar'])
            ->dispatchIfNotYetInQueue();

        expect($driver->queue['default'])->toHaveCount(2);
    }
)->with([
    QueueJobStatus::waiting,
    QueueJobStatus::running,
    QueueJobStatus::failed,
    QueueJobStatus::finished,
    QueueJobStatus::lost,
]);
