<?php

use Stubs\Scheduler;
use Stubs\SimpleInMemoryDriver;

return [
    'datapath' => __DIR__ . '/../datapath',

    'driver' => SimpleInMemoryDriver::class,

    'bootstrap_file' => __DIR__ . '/../bootstrap.php',

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
        'active' => true,
    ],
];
