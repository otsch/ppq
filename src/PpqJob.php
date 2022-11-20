<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Contracts\QueueableJob;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;

abstract class PpqJob implements QueueableJob
{
    public function __construct(protected LoggerInterface $logger = new EchoLogger())
    {
    }
}
