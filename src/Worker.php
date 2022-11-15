<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;

class Worker
{
    /**
     * @var Queue[]
     */
    private array $queues;

    private bool $stopScheduled = false;

    private ?Signal $signal = null;

    private LoggerInterface $logger;

    public function __construct(
        private readonly float $checkEveryXSeconds = 0.2,
    ) {
        $this->queues = Config::getQueues();

        $this->logger = new EchoLogger();
    }

    /**
     * @throws Exception
     */
    public function workQueues(): void
    {
        if ($this->queuesAreAlreadyWorking()) {
            throw new Exception('Queues are already working');
        }

        $nextCheck = microtime(true);

        while (true) { // @phpstan-ignore-line
            $now = microtime(true);

            if ($nextCheck > $now) {
                usleep((int) ($nextCheck * 1000000) - (int) ($now * 1000000));
            }

            $nextCheck = microtime(true) + $this->checkEveryXSeconds;

            if ($this->stopScheduled) {
                if ($this->runningJobs() > 0) {
                    continue;
                } else {
                    $this->stop();
                }
            }

            $this->checkSignals();

            $this->checkQueues();
        }
    }

    /**
     * @throws Exception
     */
    private function checkSignals(): void
    {
        if ($this->signal()?->isStop()) {
            $this->scheduleStop();

            if ($this->runningJobs() === 0) {
                $this->stop();
            }
        }
    }

    private function checkQueues(): void
    {
        $this->startWaitingJobs();

        $this->clearRunningJobs();

        $this->clearDoneJobs();
    }

    private function runningJobs(): int
    {
        $count = 0;

        foreach ($this->queues as $queue) {
            $count += $queue->runningProcessesCount();
        }

        return $count;
    }

    private function startWaitingJobs(): void
    {
        $driver = Config::getDriver();

        foreach ($this->queueNames() as $queueName) {
            $waitingJobs = $driver->where($queueName, status: QueueJobStatus::waiting);

            foreach ($waitingJobs as $waitingJob) {
                $queue = $this->getQueue($waitingJob->queue);

                if ($queue && $queue->hasAvailableSlot()) {
                    $queue->startWaitingJob($waitingJob);
                }
            }
        }
    }

    private function clearRunningJobs(): void
    {
        foreach ($this->queues as $queue) {
            $queue->clearRunningJobs();
        }
    }

    private function scheduleStop(): void
    {
        $this->stopScheduled = true;

        $this->logger->info('Scheduled to stop running queues. Finish only running jobs and don\'t start new ones.');
    }

    /**
     * @throws Exception
     */
    private function stop(): void
    {
        $this->logger->info('No more running jobs, stop running queues.');

        $this->signal()?->reset();

        exit;
    }

    /**
     * @return string[]
     */
    private function queueNames(): array
    {
        return array_map(function ($queue) {
            return $queue->name;
        }, $this->queues);
    }

    private function getQueue(string $name): ?Queue
    {
        return $this->queues[$name] ?? null;
    }

    private function clearDoneJobs(): void
    {
        $driver = Config::getDriver();

        foreach ($this->queues as $queue) {
            $allQueueRecords = $driver->where($queue->name, status: null);

            if (count($allQueueRecords) <= $queue->keepLastXPastJobs) {
                continue;
            }

            $doneCount = 0;

            foreach ($allQueueRecords as $queueRecord) {
                if ($queueRecord->status->isPast()) {
                    $doneCount++;

                    if ($doneCount > $queue->keepLastXPastJobs) {
                        $driver->forget($queueRecord->id);
                    }
                }
            }
        }
    }

    private function queuesAreAlreadyWorking(): bool
    {
        return Process::runningProcessContainingStringsExists(['php', 'vendor/bin/ppq', 'work']);
    }

    private function signal(): ?Signal
    {
        if (!$this->signal) {
            try {
                $this->signal = new Signal();
            } catch (Exception $exception) {
                $this->logger->warning('Checking signals doesn\'t work: ' . $exception->getMessage());
            }
        }

        return $this->signal;
    }
}
