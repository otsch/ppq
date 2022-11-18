<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueableJob;

class TestJob implements QueueableJob
{
    public function __construct(protected readonly int $countTo = 999999)
    {
    }

    public function invoke(): void
    {
        for ($i = 0; $i < $this->countTo; $i++) {
        }

        echo "Successfully finished TestJob";
    }
}
