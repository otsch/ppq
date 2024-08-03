<?php

namespace Stubs;

use Throwable;

class ErrorHandler extends AbstractTestErrorHandler
{
    public function boot(): void
    {
        file_put_contents(
            __DIR__ . '/../_testdata/datapath/error-handler-events',
            'error reporting: ' . error_reporting(),
            FILE_APPEND,
        );

        $this->registerHandler(function (Throwable $exception) {
            $this->logErrorEvent($exception);
        });
    }
}
