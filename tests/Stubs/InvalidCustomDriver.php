<?php

namespace Stubs;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

class InvalidCustomDriver
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

    /**
     * @param mixed[]|null $args
     * @return QueueRecord[]
     */
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
