<?php

namespace Otsch\Ppq;

use Closure;

class Utils
{
    public static function tryUntil(
        Closure $callback,
        int $maxTries = 100,
        int $sleep = 10000
    ): mixed {
        $tries = 0;

        while (!($callbackReturnValue = $callback()) && $tries < $maxTries) {
            usleep($sleep);

            $tries++;
        }

        return $callbackReturnValue;
    }
}
