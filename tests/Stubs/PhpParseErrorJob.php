<?php

namespace Stubs;

use Otsch\Ppq\PpqJob;

class PhpParseErrorJob extends PpqJob
{
    /**
     * Job to cause a PHP parse error, for testing error handlers.
     */
    public function invoke(): void
    {
        syntax error
    }
}
