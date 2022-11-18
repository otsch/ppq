<?php

namespace Otsch\Ppq\Entities\Values;

use Exception;

enum QueueJobStatus
{
    case waiting;

    case running;

    case finished;

    case failed;

    case lost;

    case cancelled;

    /**
     * @throws Exception
     */
    public static function fromString(string $status): QueueJobStatus
    {
        return match ($status) {
            'waiting' => QueueJobStatus::waiting,
            'running' => QueueJobStatus::running,
            'finished' => QueueJobStatus::finished,
            'failed' => QueueJobStatus::failed,
            'lost' => QueueJobStatus::lost,
            'cancelled' => QueueJobStatus::cancelled,
            default => throw new Exception('Invalid queue job status ' . $status),
        };
    }

    public function isPast(): bool
    {
        return in_array($this, [
            QueueJobStatus::finished,
            QueueJobStatus::failed,
            QueueJobStatus::lost,
            QueueJobStatus::cancelled,
        ], true);
    }
}
