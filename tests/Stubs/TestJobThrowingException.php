<?php

namespace Stubs;

use Exception;
use Otsch\Ppq\Contracts\QueueableJob;

class TestJobThrowingException implements QueueableJob
{
    public function invoke(): void
    {
        throw new Exception('Something went wrong');
    }
}
