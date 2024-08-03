<?php

namespace Stubs;

use Exception;
use Otsch\Ppq\PpqJob;

class ExceptionJob extends PpqJob
{
    /**
     * @throws Exception
     */
    public function invoke(): void
    {
        throw new Exception('This is an uncaught test exception');
    }
}
