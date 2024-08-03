<?php

namespace Stubs;

use Throwable;

class ErrorHandlerIgnoreWarnings extends AbstractTestErrorHandler
{
    public function boot(): void
    {
        $this->registerHandler(function (Throwable $exception) {
            $this->logErrorEvent($exception);
        }, true); // Set second parameter "ignoreWarnings" to true.
    }
}
