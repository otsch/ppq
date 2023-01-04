<?php

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\QueueEventListeners;
use Stubs\Listeners\DefaultFinished;
use Stubs\Listeners\OtherQueueFailedOne;
use Stubs\Listeners\OtherQueueFailedTwo;
use Stubs\Listeners\TestEventListener;
use Stubs\TestJob;

beforeEach(function () {
    TestEventListener::$staticCalled = false;
});

it(
    'takes an array with type (waiting, running,...) as key and either single listener class names or an array of ' .
    'listener class names as constructor argument',
    function () {
        $listeners = new QueueEventListeners([
            'finished' => DefaultFinished::class,
            'failed' => [OtherQueueFailedOne::class, OtherQueueFailedTwo::class],
        ]);

        expect($listeners)->toBeInstanceOf(QueueEventListeners::class);
    }
);

it('calls single listeners for some event', function (string $eventName) {
    $listeners = new QueueEventListeners([$eventName => TestEventListener::class]);

    expect(TestEventListener::$staticCalled)->toBeFalse();

    $eventMethodName = 'call' . ucfirst($eventName);

    $listeners->$eventMethodName(new QueueRecord('default', TestJob::class));

    expect(TestEventListener::$staticCalled)->toBeTrue();
})->with(['waiting', 'running', 'finished', 'failed', 'lost', 'cancelled']);

it('calls multiple listeners for some event', function (string $eventName) {
    $listenerOne = new TestEventListener();

    expect($listenerOne->called)->toBeFalse();

    $listenerTwo = new TestEventListener();

    expect($listenerTwo->called)->toBeFalse();

    $listenerThree = new TestEventListener();

    expect($listenerThree->called)->toBeFalse();

    $listeners = new QueueEventListeners([
        $eventName => [$listenerOne, $listenerTwo, $listenerThree]
    ]);

    $eventMethodName = 'call' . ucfirst($eventName);

    $listeners->$eventMethodName(new QueueRecord('default', TestJob::class));

    expect($listenerOne->called)->toBeTrue();

    expect($listenerTwo->called)->toBeTrue();

    expect($listenerThree->called)->toBeTrue();
})->with(['waiting', 'running', 'finished', 'failed', 'lost', 'cancelled']);
