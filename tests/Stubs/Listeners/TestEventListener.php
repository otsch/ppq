<?php

namespace Stubs\Listeners;

use Otsch\Ppq\Contracts\QueueEventListener;
use Otsch\Ppq\Entities\QueueRecord;

class TestEventListener implements QueueEventListener
{
    public static bool $staticCalled = false;

    public bool $called = false;

    public function invoke(QueueRecord $queueRecord): void
    {
        $this->called = true;

        self::$staticCalled = true;
    }
}
