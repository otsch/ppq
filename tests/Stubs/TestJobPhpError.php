<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueableJob;

class TestJobPhpError implements QueueableJob
{
    public function invoke(): void
    {
        $foo = 'bar';

        var_dumb($foo); // @phpstan-ignore-line
    }
}
