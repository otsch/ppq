<?php

namespace Stubs;

use Otsch\Ppq\PpqJob;

class LogLinesTestJob extends PpqJob
{
    public function invoke(): void
    {
        for ($i = 1; $i <= 3000; $i++) {
            $this->logger->info((string) $i);
        }
    }
}
