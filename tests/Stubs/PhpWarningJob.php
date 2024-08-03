<?php

namespace Stubs;

use Otsch\Ppq\PpqJob;

class PhpWarningJob extends PpqJob
{
    /**
     * Job to cause a PHP warning, for testing error handlers.
     */
    public function invoke(): void
    {
        $content = file_get_contents(__DIR__ . '/file-that-does-not-exist');
    }
}
