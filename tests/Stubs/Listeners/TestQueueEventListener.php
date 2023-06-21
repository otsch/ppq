<?php

namespace Stubs\Listeners;

use Otsch\Ppq\Contracts\QueueEventListener;

abstract class TestQueueEventListener implements QueueEventListener
{
    protected function dataPath(string $withinPath = ''): string
    {
        if (!empty($withinPath)) {
            $withinPath = str_starts_with($withinPath, '/') ? $withinPath : '/' . $withinPath;
        }

        return __DIR__ . '/../../_testdata/datapath' . $withinPath;
    }
}
