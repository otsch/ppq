<?php

namespace Otsch\Ppq\Contracts;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

interface QueueDriver
{
    public function add(QueueRecord $queueRecord): void;

    public function update(QueueRecord $queueRecord): void;

    public function get(string $id): ?QueueRecord;

    public function forget(string $id): void;

    /**
     * Get filtered list of queue jobs matching all criteria.
     * Only mandatory parameter is the queue name.
     *
     * @param mixed[] $args
     * @return QueueRecord[]
     */
    public function where(
        string $queue,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): array;
}
