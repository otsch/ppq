<?php

use Otsch\Ppq\AbstractErrorHandler;

it('calls all registered handlers with uncaught exceptions', function () {
    $handler = new class () extends AbstractErrorHandler {
        /**
         * @var Throwable[]
         */
        public array $_exceptions = [];

        /**
         * @var Throwable[]
         */
        public array $_exceptions2 = [];

        public function boot(): void
        {
            $this->registerHandler(function (Throwable $exception) {
                $this->_exceptions[] = $exception;
            });

            $this->registerHandler(function (Throwable $exception) {
                $this->_exceptions2[] = $exception;
            });
        }
    };

    $exception = new InvalidArgumentException('test test');

    $handler->handleException($exception);

    expect($handler->_exceptions[0])->toBe($exception)
        ->and($handler->_exceptions2[0])->toBe($exception);

    $handler->deactivate(); // So it does not influence other tests running in the same process.
});
