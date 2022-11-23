<?php

namespace Stubs;

use Otsch\Ppq\PpqJob;

class LogTestJob extends PpqJob
{
    public function invoke(): void
    {
        $this->logger->info('some info');

        $this->logger->warning('ohoh, this is a warning');

        $this->logger->notice('Just want you to know that...');
    }
}
