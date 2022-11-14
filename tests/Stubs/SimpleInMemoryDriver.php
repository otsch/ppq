<?php

namespace Stubs;

use Otsch\Ppq\Drivers\AbstractQueueDriver;
use Otsch\Ppq\Entities\QueueRecord;

class SimpleInMemoryDriver extends AbstractQueueDriver
{
    /**
     * @var QueueRecord[]
     */
    public array $queue = [];

    public function add(QueueRecord $queueRecord): void
    {
        $this->queue[$queueRecord->id] = $queueRecord;
    }

    public function update(QueueRecord $queueRecord): void
    {
        if (isset($this->queue[$queueRecord->id])) {
            $this->queue[$queueRecord->id] = $queueRecord;
        }
    }

    public function get(string $id): ?QueueRecord
    {
        if (isset($this->queue[$id])) {
            return $this->queue[$id];
        }

        return null;
    }

    public function forget(string $id): void
    {
        if (isset($this->queue[$id])) {
            unset($this->queue[$id]);
        }
    }

    protected function getQueue(string $queue): array
    {
        return $this->queue;
    }
}
