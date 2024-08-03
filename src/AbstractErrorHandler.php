<?php

namespace Otsch\Ppq;

use Closure;
use ErrorException;
use Throwable;

abstract class AbstractErrorHandler
{
    /**
     * @var Closure[]
     */
    protected array $handlers = [];

    private bool $active = true;

    /**
     * @var callable|null
     */
    private mixed $initialHandlers = null;

    public function __construct()
    {
        $this->boot();
    }

    abstract public function boot(): void;

    public function handleException(Throwable $exception): void
    {
        if ($this->active) {
            foreach ($this->handlers as $handler) {
                $handler($exception);
            }
        }
    }

    public function deactivate(): void
    {
        $this->active = false;

        if (!empty($this->handlers) && !empty($this->initialHandlers)) {
            set_error_handler($this->initialHandlers);
        }
    }

    protected function registerHandler(Closure $handler, bool $ignoreWarnings = false): static
    {
        $firstHandler = empty($this->handlers);

        $this->handlers[] = $handler;

        $initialHandlers = set_error_handler(
            function (
                int $errno,
                string $errstr,
                string $errfile,
                int $errline,
            ) use ($handler) {
                $handler(new ErrorException($errstr, 0, $errno, $errfile, $errline));

                return false;
            },
            $ignoreWarnings ? E_ALL & ~E_WARNING & ~E_NOTICE : E_ALL & ~E_NOTICE,
        );

        if ($firstHandler) {
            $this->initialHandlers = $initialHandlers;

            register_shutdown_function(function () use ($handler) {
                if ($this->active) {
                    $error = error_get_last();

                    if ($error && in_array($error['type'], [E_ERROR, E_PARSE], true)) {
                        $handler(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
                    }
                }
            });
        }

        return $this;
    }
}
