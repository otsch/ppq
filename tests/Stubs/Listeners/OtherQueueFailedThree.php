<?php

namespace Stubs\Listeners;

use Otsch\Ppq\Entities\QueueRecord;

class OtherQueueFailedThree extends TestQueueEventListener
{
    public function invoke(QueueRecord $queueRecord): void
    {
        file_put_contents(
            $this->dataPath('event-listeners-check-file'),
            'other queue failed three called',
            FILE_APPEND,
        );
    }
}
