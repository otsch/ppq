<?php

namespace Stubs;

use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

class CustomDriver implements QueueDriver
{
    public function add(QueueRecord $queueRecord): void
    {
    }

    public function update(QueueRecord $queueRecord): void
    {
    }

    public function get(string $id): ?QueueRecord
    {
        return null;
    }

    public function forget(string $id): void
    {
    }

    public function where(
        string $queue,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): array {
        return [];
    }
}
