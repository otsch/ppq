<?php

use Otsch\Ppq\Drivers\FileDriver;

return [
    'datapath' => __DIR__ . '/../datapath',

    'driver' => FileDriver::class,

    'bootstrap_file' => __DIR__ . '/../bootstrap.php',

    'queues' => [
        'default' => [
            'concurrent_jobs' => 2,
            'keep_last_x_past_jobs' => 10,
        ],
        'other_queue' => [
            'concurrent_jobs' => 3,
            'keep_last_x_past_jobs' => 10,
        ],
        'infinite_waiting_jobs_queue' => [
            'concurrent_jobs' => 0,
        ],
    ],

    'error_reporting' => 'E_ALL',
];
