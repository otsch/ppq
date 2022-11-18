<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueableJob;

class OtherTestJob implements QueueableJob
{
    public function invoke(): void
    {
        echo "Successfully finished the OtherTestJob";
    }
}
