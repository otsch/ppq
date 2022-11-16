<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueableJob;

class TestJob implements QueueableJob
{
    public function invoke(): void
    {
        for ($i = 0; $i < 999999; $i++) {
        }

        echo "Successfully finished TestJob";
    }
}
