<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Exception;

class Dispatcher
{
    private string $jobClassName = '';

    /**
     * @var mixed[]
     */
    private array $args = [];

    public function __construct(
        private readonly string $queueName,
        private readonly QueueDriver $driver = new FileDriver(),
    ) {
    }

    public static function queue(string $name): self
    {
        return new self($name, Config::getDriver());
    }

    public function job(string $jobClassName): self
    {
        $this->jobClassName = $jobClassName;

        return $this;
    }

    /**
     * @param mixed[] $args
     */
    public function args(array $args): self
    {
        $this->args = $args;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function dispatch(): QueueRecord
    {
        $this->errorIfJobClassMissing();

        $record = new QueueRecord($this->queueName, $this->jobClassName, args: $this->args);

        $this->driver->add($record);

        $this->reset();

        return $record;
    }

    /**
     * @param null|string|string[] $matchArgs
     * @throws Exception
     */
    public function dispatchIfNotYetInQueue(null|string|array $matchArgs = null): ?QueueRecord
    {
        $this->errorIfJobClassMissing();

        if ($this->matchesWaitingOrRunningQueueJob($matchArgs)) {
            return null;
        }

        return $this->dispatch();
    }

    /**
     * @param null|string|string[] $matchArgs
     * @return bool
     * @throws Exception
     */
    private function matchesWaitingOrRunningQueueJob(null|string|array $matchArgs): bool
    {
        $matchArgs = $this->prepareMatchArgs($matchArgs);

        $matchingWaitingJobInQueue = $this->driver->where(
            $this->queueName,
            $this->jobClassName,
            QueueJobStatus::waiting,
            $matchArgs
        );

        if (!empty($matchingWaitingJobInQueue)) {
            return true;
        }

        $matchingRunningJobInQueue = $this->driver->where(
            $this->queueName,
            $this->jobClassName,
            QueueJobStatus::running,
            $matchArgs
        );

        if (!empty($matchingRunningJobInQueue)) {
            return true;
        }

        return false;
    }

    /**
     * @param null|string|string[] $matchArgs
     * @return mixed[]
     */
    private function prepareMatchArgs(null|string|array $matchArgs): array
    {
        if ($matchArgs) {
            $matchArgs = !is_array($matchArgs) ? [$matchArgs] : $matchArgs;
        }

        return $matchArgs ? array_intersect_key($this->args, array_flip($matchArgs)) : $this->args;
    }

    /**
     * @throws Exception
     */
    private function errorIfJobClassMissing(): void
    {
        if (empty($this->jobClassName)) {
            throw new Exception('You need to set a Job class to queue.');
        }
    }

    private function reset(): void
    {
        $this->jobClassName = '';

        $this->args = [];
    }
}
