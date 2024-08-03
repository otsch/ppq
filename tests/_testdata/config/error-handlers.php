<?php

use Stubs\ErrorHandler;

return [
    'datapath' => __DIR__ . '/../datapath',

    'queues' => [
        'default' => [
            'concurrent_jobs' => 2,
        ],
    ],

    'error_reporting' => 'E_ALL',

    'error_handler' => [
        'class' => ErrorHandler::class,
        'active' => true,
    ],
];
