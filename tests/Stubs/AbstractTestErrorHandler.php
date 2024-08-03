<?php

namespace Stubs;

use ErrorException;
use Otsch\Ppq\AbstractErrorHandler;
use Throwable;

abstract class AbstractTestErrorHandler extends AbstractErrorHandler
{
    protected function logErrorEvent(Throwable $exception): void
    {
        if ($exception instanceof ErrorException) {
            if ($exception->getSeverity() === E_WARNING) {
                $message = 'PHP Warning: ' . $exception->getMessage();
            } elseif ($exception->getSeverity() === E_ERROR) {
                $message = 'PHP Error: ' . $exception->getMessage();
            } elseif ($exception->getSeverity() === E_PARSE) {
                $message = 'PHP Parse Error: ' . $exception->getMessage();
            } else {
                $message = 'PHP Error (Severity ' . $exception->getSeverity() . '): ' . $exception->getMessage();
            }
        } else {
            $message = get_class($exception) . ': ' . $exception->getMessage();
        }

        file_put_contents(
            __DIR__ . '/../_testdata/datapath/error-handler-events',
            $message . PHP_EOL,
            FILE_APPEND,
        );
    }
}
