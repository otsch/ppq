<?php

use Stubs\ErrorHandlerIgnoreWarnings;

return [
    'datapath' => __DIR__ . '/../datapath',

    'queues' => [
        'default' => [
            'concurrent_jobs' => 2,
        ],
    ],

    'error_handler' => [
        'class' => ErrorHandlerIgnoreWarnings::class,
        'active' => true,
    ],
];
