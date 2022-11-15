<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueableJob;

class TestJob implements QueueableJob
{
    public function invoke(): void
    {
        usleep(rand(10000, 20000));

        echo "Successfully finished TestJob";
    }
}
