<?php

namespace Stubs;

use Throwable;

class ErrorHandler extends AbstractTestErrorHandler
{
    public function boot(): void
    {
        $this->registerHandler(function (Throwable $exception) {
            $this->logErrorEvent($exception);
        });
    }
}
