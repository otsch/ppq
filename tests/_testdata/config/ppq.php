<?php

use Otsch\Ppq\Drivers\FileDriver;
use Stubs\Scheduler;

return [
    'datapath' => __DIR__ . '/../datapath',

    'driver' => FileDriver::class,

    'bootstrap_file' => null,

    'queues' => [
        'default' => [
            'concurrent_jobs' => 6,
        ],
        'other_queue' => [
            'concurrent_jobs' => 3,
        ],
    ],

    'scheduler' => [
        'class' => Scheduler::class,
        'active' => false,
    ],
];
