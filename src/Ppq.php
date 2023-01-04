<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Exceptions\MissingDataPathException;

class Ppq
{
    /**
     * @return string[]
     */
    public static function queueNames(): array
    {
        return Config::getQueueNames();
    }

    public static function find(string $id): ?QueueRecord
    {
        return Config::getDriver()->get($id);
    }

    public static function cancel(string $id): void
    {
        $queueRecord = self::find($id);

        if ($queueRecord && !$queueRecord->status->isPast()) {
            if ($queueRecord->status === QueueJobStatus::waiting) {
                self::callCancelledListeners($queueRecord);
            }

            $queueRecord->status = QueueJobStatus::cancelled;

            Config::getDriver()->update($queueRecord);
        }
    }

    public static function clear(string $queueName): void
    {
        if (in_array($queueName, self::queueNames(), true)) {
            Config::getDriver()->clear($queueName);
        }
    }

    public static function clearAll(): void
    {
        foreach (self::queueNames() as $queueName) {
            Config::getDriver()->clear($queueName);
        }
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function waiting(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::waiting, $queueName);
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function running(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::running, $queueName);
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function finished(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::finished, $queueName);
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function failed(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::failed, $queueName);
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function cancelled(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::cancelled, $queueName);
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function lost(?string $queueName = null): array
    {
        return self::whereStatus(QueueJobStatus::lost, $queueName);
    }

    /**
     * @param mixed[] $args
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    public static function where(
        string $queue,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = null,
        ?array $args = null,
        ?int $pid = null,
    ): array {
        return Config::getDriver()->where($queue, $jobClassName, $status, $args, $pid);
    }

    public static function dataPath(string $pathWithinDataPath = ''): string
    {
        $path = Config::get('datapath');

        if (!$path) {
            throw new MissingDataPathException('No datapath defined in config.');
        }

        return $path . (!str_ends_with($path, '/') ? '/' : '') . $pathWithinDataPath;
    }

    /**
     * @return QueueRecord[]
     * @throws Exceptions\InvalidQueueDriverException
     */
    protected static function whereStatus(QueueJobStatus $status, ?string $queueName): array
    {
        if ($queueName) {
            return Config::getDriver()->where($queueName, status: $status);
        }

        $waitingQueueRecords = [];

        foreach (Config::getQueues() as $queueName => $queue) {
            $waitingQueueRecords = array_merge(
                $waitingQueueRecords,
                Config::getDriver()->where($queueName, status: $status),
            );
        }

        return $waitingQueueRecords;
    }

    protected static function callCancelledListeners(QueueRecord $queueRecord): void
    {
        $queues = Config::getQueues();

        if (isset($queues[$queueRecord->queue])) {
            $queue = $queues[$queueRecord->queue];

            $queue->eventListeners->callCancelled($queueRecord);
        }
    }
}
