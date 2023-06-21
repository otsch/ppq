<?php

namespace Stubs\Listeners;

use Otsch\Ppq\Entities\QueueRecord;

class DefaultCancelled extends TestQueueEventListener
{
    public function invoke(QueueRecord $queueRecord): void
    {
        file_put_contents($this->dataPath('event-listeners-check-file'), 'default cancelled called');
    }
}
