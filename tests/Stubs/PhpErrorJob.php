<?php

namespace Stubs;

use Otsch\Ppq\PpqJob;

class PhpErrorJob extends PpqJob
{
    /**
     * Job to cause a PHP error, for testing error handlers.
     */
    public function invoke(): void
    {
        $this->nonExistingFunction(); // @phpstan-ignore-line
    }
}
