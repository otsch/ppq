<?php

namespace Stubs\Listeners;

use Otsch\Ppq\Contracts\QueueEventListener;
use Otsch\Ppq\Entities\QueueRecord;

class OtherQueueFailedOne implements QueueEventListener
{
    public function invoke(QueueRecord $queueRecord): void
    {
        file_put_contents(
            __DIR__ . '/../../_testdata/datapath/event-listeners-check-file',
            'other queue failed one called ',
            FILE_APPEND,
        );
    }
}
