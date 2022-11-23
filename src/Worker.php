<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;

class Worker
{
    /**
     * @var null|Queue[]
     */
    private ?array $queues = null;

    private bool $stopScheduled = false;

    private LoggerInterface $logger;

    public function __construct(
        protected readonly float $checkEveryXSeconds = 0.2,
        protected Signal $signal = new Signal(),
    ) {
        $this->logger = new EchoLogger();
    }

    /**
     * @throws Exception
     */
    public function workQueues(): void
    {
        if (WorkerProcess::isWorking()) {
            throw new Exception('Queues are already working');
        }

        $this->logger->info('Start working queues');

        WorkerProcess::set();

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

            WorkerProcess::heartbeat();
        }
    }

    /**
     * @return Queue[]
     */
    private function queues(): array
    {
        if (!$this->queues) {
            $this->queues = Config::getQueues();
        }

        return $this->queues;
    }

    /**
     * @throws Exception
     */
    private function checkSignals(): void
    {
        if ($this->signal->isStop()) {
            $this->scheduleStop();

            if ($this->runningJobs() === 0) {
                $this->stop();
            }
        }
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    private function checkQueues(): void
    {
        $this->cancelCancelledRunningJobs();

        $this->startWaitingJobs();

        $this->clearRunningJobs();

        $this->clearDoneJobs();
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    private function runningJobs(): int
    {
        $count = 0;

        foreach ($this->queues() as $queue) {
            $count += $queue->runningProcessesCount();
        }

        return $count;
    }

    private function cancelCancelledRunningJobs(): void
    {
        foreach ($this->queues() as $queue) {
            $queue->cancelCancelledRunningJobs();
        }
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
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

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    private function clearRunningJobs(): void
    {
        foreach ($this->queues() as $queue) {
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

        $this->signal->reset();

        exit;
    }

    /**
     * @return string[]
     */
    private function queueNames(): array
    {
        return array_map(function ($queue) {
            return $queue->name;
        }, $this->queues());
    }

    private function getQueue(string $name): ?Queue
    {
        return $this->queues()[$name] ?? null;
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    private function clearDoneJobs(): void
    {
        foreach ($this->queues() as $queue) {
            $this->clearDoneJobsInQueue($queue);
        }
    }

    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    protected function clearDoneJobsInQueue(Queue $queue): void
    {
        $driver = Config::getDriver();

        $allQueueRecords = $driver->where($queue->name, status: null);

        if (count($allQueueRecords) <= $queue->keepLastXPastJobs) {
            return;
        }

        $doneCount = $this->getDoneCount($allQueueRecords);

        if ($doneCount > $queue->keepLastXPastJobs) {
            $doneJobs = $this->getSortedDoneJobs($allQueueRecords);

            $this->clearSortedDoneJobs($doneJobs, $doneCount, $driver, $queue);
        }
    }

    /**
     * @param QueueRecord[] $allQueueRecords
     * @return int
     */
    protected function getDoneCount(array $allQueueRecords): int
    {
        $count = 0;

        foreach ($allQueueRecords as $queueRecord) {
            if ($queueRecord->status->isPast()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param mixed[] $doneJobs
     */
    protected function clearSortedDoneJobs(array $doneJobs, int $doneCount, QueueDriver $driver, Queue $queue): void
    {
        while ($doneCount > $queue->keepLastXPastJobs) {
            foreach ($doneJobs as $oldestDoneJobs) {
                foreach ($oldestDoneJobs as $oldestDoneJob) {
                    if ($doneCount <= $queue->keepLastXPastJobs) {
                        break 2;
                    }

                    $driver->forget($oldestDoneJob->id);

                    Logs::forget($oldestDoneJob);

                    $doneCount -= 1;
                }
            }
        }
    }

    /**
     * From all jobs of the queue, get the jobs that are already done (finished, failed, lost).
     * In order to remove jobs first, that have finished first, deliver an array with the doneTime as key sorted
     * ascending.
     * All jobs, that for some reason don't have a doneTime, are probably older than the ones with doneTime, so it
     * adds them with older doneTimes.
     *
     * @param mixed[] $allQueueRecords
     * @return mixed[]
     */
    protected function getSortedDoneJobs(array $allQueueRecords): array
    {
        $doneCount = 0;

        $doneJobs = [];

        $olderDoneJobs = [];

        $oldestDoneTime = Utils::currentMicrosecondsInt();

        foreach ($allQueueRecords as $queueRecord) {
            if ($queueRecord->status->isPast()) {
                $doneCount++;

                if (!$queueRecord->doneTime) {
                    $olderDoneJobs[] = $queueRecord;
                } else {
                    $doneJobs[$queueRecord->doneTime][] = $queueRecord;
                }

                if ($queueRecord->doneTime < $oldestDoneTime) {
                    $oldestDoneTime = $queueRecord->doneTime;
                }
            }
        }

        if (!empty($olderDoneJobs)) {
            foreach (array_reverse($olderDoneJobs) as $olderDoneJob) {
                $oldestDoneTime -= 1;

                $doneJobs[$oldestDoneTime][] = $olderDoneJob;
            }
        }

        ksort($doneJobs);

        return $doneJobs;
    }
}
