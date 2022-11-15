<?php

namespace Stubs;

use Otsch\Ppq\Drivers\AbstractQueueDriver;
use Otsch\Ppq\Entities\QueueRecord;

class SimpleInMemoryDriver extends AbstractQueueDriver
{
    /**
     * @var array<string, array<string, QueueRecord>>
     */
    public array $queue = [];

    public function add(QueueRecord $queueRecord): void
    {
        $this->queue[$queueRecord->queue][$queueRecord->id] = $queueRecord;
    }

    public function update(QueueRecord $queueRecord): void
    {
        if (isset($this->queue[$queueRecord->queue][$queueRecord->id])) {
            $this->queue[$queueRecord->queue][$queueRecord->id] = $queueRecord;
        }
    }

    public function get(string $id): ?QueueRecord
    {
        foreach ($this->queue as $queueName => $queueJobs) {
            if (isset($this->queue[$queueName][$id])) {
                return $this->queue[$queueName][$id];
            }
        }

        return null;
    }

    public function forget(string $id): void
    {
        $queueJob = $this->get($id);

        if ($queueJob) {
            unset($this->queue[$queueJob->queue][$id]);
        }
    }

    protected function getQueue(string $queue): array
    {
        return $this->queue[$queue] ?? [];
    }
}
