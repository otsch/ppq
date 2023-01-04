<?php

namespace Otsch\Ppq\Contracts;

use Otsch\Ppq\Entities\QueueRecord;

interface QueueEventListener
{
    public function invoke(QueueRecord $queueRecord): void;
}
