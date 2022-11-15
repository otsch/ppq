<?php

use Otsch\Ppq\Drivers\FileDriver;

return [
    'datapath' => __DIR__ . '/../datapath',

    'driver' => FileDriver::class,

    'bootstrap_file' => __DIR__ . '/../bootstrap.php',

    'queues' => [
        'default' => [
            'concurrent_jobs' => 2,
        ],
        'other_queue' => [
            'concurrent_jobs' => 3,
        ],
    ],
];
