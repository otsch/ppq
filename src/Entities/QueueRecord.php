<?php

namespace Otsch\Ppq\Entities;

use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Exception;

final class QueueRecord
{
    public readonly string $id;

    /**
     * @param mixed[] $args
     */
    public function __construct(
        public readonly string $queue,
        public readonly string $jobClass,
        public QueueJobStatus $status = QueueJobStatus::waiting,
        public readonly array $args = [],
        public ?int $pid = null,
        ?string $id = null,
        public ?int $doneTime = null,
    ) {
        $this->id = $id ?? uniqid((string) rand(1, 1000000), more_entropy: true);
    }

    /**
     * @param mixed[] $data
     * @throws Exception
     */
    public static function fromArray(array $data): QueueRecord
    {
        if (!isset($data['queue']) || !isset($data['jobClass'])) {
            throw new Exception('Invalid queue job record');
        }

        if (is_string($data['status'])) {
            $data['status'] = QueueJobStatus::fromString($data['status']);
        }

        return new QueueRecord(
            $data['queue'],
            $data['jobClass'],
            $data['status'] ?? QueueJobStatus::waiting,
            $data['args'] ?? [],
            $data['pid'] ?? null,
            $data['id'] ?? null,
            $data['doneTime'] ?? null,
        );
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->queue,
            'jobClass' => $this->jobClass,
            'status' => $this->status->name,
            'args' => $this->args,
            'pid' => $this->pid,
            'doneTime' => $this->doneTime,
        ];
    }

    public function setDoneNow(): void
    {
        $this->doneTime = (int) (microtime(true) * 1000000);
    }
}
