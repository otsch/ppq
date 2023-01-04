<?php

use Otsch\Ppq\Drivers\FileDriver;
use Stubs\Listeners\DefaultCancelled;
use Stubs\Listeners\DefaultFailed;
use Stubs\Listeners\DefaultFinished;
use Stubs\Listeners\DefaultLost;
use Stubs\Listeners\DefaultRunning;
use Stubs\Listeners\DefaultWaiting;
use Stubs\Listeners\OtherQueueFailedOne;
use Stubs\Listeners\OtherQueueFailedThree;
use Stubs\Listeners\OtherQueueFailedTwo;

return [
    'datapath' => __DIR__ . '/../datapath',

    'driver' => FileDriver::class,

    'queues' => [
        'default' => [
            'concurrent_jobs' => 2,
            'listeners' => [
                'waiting' => [DefaultWaiting::class],
                'running' => DefaultRunning::class,
                'finished' => [DefaultFinished::class],
                'failed' => DefaultFailed::class,
                'lost' => DefaultLost::class,
                'cancelled' => DefaultCancelled::class,
            ]
        ],
        'other_queue' => [
            'concurrent_jobs' => 2,
            'listeners' => [
                'failed' => [OtherQueueFailedOne::class, OtherQueueFailedTwo::class, OtherQueueFailedThree::class],
            ]
        ],
    ],
];
