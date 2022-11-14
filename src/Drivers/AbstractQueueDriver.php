<?php

namespace Otsch\Ppq\Drivers;

use Exception;
use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

abstract class AbstractQueueDriver implements QueueDriver
{
    /**
     * @param mixed[]|null $args
     * @return QueueRecord[]
     * @throws Exception
     */
    public function where(
        string $queue,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): array {
        $queueRecords = $this->getQueue($queue);

        $filtered = [];

        foreach ($queueRecords as $id => $queueRecord) {
            if ($this->matchesFilters($queueRecord, $jobClassName, $status, $args, $pid)) {
                $filtered[$id] = $queueRecord;
            }
        }

        return $filtered;
    }

    /**
     * @param string $queue
     * @return QueueRecord[]
     */
    abstract protected function getQueue(string $queue): array;

    /**
     * @param mixed[]|null $args
     */
    protected function matchesFilters(
        QueueRecord $record,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): bool {
        if ($jobClassName !== null && $record->jobClass !== $jobClassName) {
            return false;
        }

        if ($status !== null && $record->status !== $status) {
            return false;
        }

        if ($args !== null && !$this->matchesArgFilters($record, $args)) {
            return false;
        }

        if ($pid !== null && $pid !== $record->pid) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed[] $matchArgs
     */
    protected function matchesArgFilters(QueueRecord $record, array $matchArgs): bool
    {
        if ($matchArgs === [] && !empty($record->args)) {
            return false;
        }

        foreach ($matchArgs as $key => $value) {
            if (!isset($record->args[$key]) || $record->args[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
