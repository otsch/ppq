<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Exceptions\InvalidQueueDriverException;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class Queue
{
    /**
     * @var Process[]
     */
    private array $processes = [];

    private LoggerInterface $logger;

    public function __construct(
        public readonly string $name,
        public readonly int $concurrentJobs,
        public readonly int $keepLastXPastJobs,
    ) {
        $this->logger = new EchoLogger();
    }

    /**
     * @throws InvalidQueueDriverException
     */
    public function hasAvailableSlot(): bool
    {
        return $this->runningProcessesCount() < $this->concurrentJobs;
    }

    /**
     * @throws InvalidQueueDriverException
     */
    public function startWaitingJob(QueueRecord $waitingJob): void
    {
        $process = Kernel::ppqCommand('run-job ' . $waitingJob->id);

        $process->start();

        $pid = $process->getPid();

        $this->logger->info('Started job with id ' . $waitingJob->id);

        $waitingJob->status = QueueJobStatus::running;

        $waitingJob->pid = $pid;

        Config::getDriver()->update($waitingJob);

        $this->processes[$pid] = new Process($waitingJob, $process);
    }

    /**
     * @throws InvalidQueueDriverException
     */
    public function clearRunningJobs(): void
    {
        foreach ($this->getForgottenRunningJobs() as $forgottenRunningJob) {
            if (!$this->isJobStillRunning($forgottenRunningJob)) {
                $this->finishForgottenJob($forgottenRunningJob);
            }
        }
    }

    /**
     * @throws InvalidQueueDriverException
     */
    public function runningProcessesCount(): int
    {
        $this->clearRunningJobs();

        return count($this->processes);
    }

    /**
     * @return QueueRecord[]
     * @throws InvalidQueueDriverException
     */
    private function getForgottenRunningJobs(): array
    {
        $runningPids = $this->getKnownRunningPids();

        $runningJobs = Config::getDriver()->where($this->name, status: QueueJobStatus::running);

        $filtered = [];

        foreach ($runningJobs as $runningJob) {
            if (!in_array($runningJob->pid, $runningPids, true)) {
                $filtered[] = $runningJob;
            }
        }

        return $filtered;
    }

    /**
     * @return array<int|string, int|string>
     */
    private function getKnownRunningPids(): array
    {
        $pids = [];

        foreach ($this->processes as $pid => $process) {
            if ($process->process->isRunning()) {
                $pids[$pid] = $pid;
            } else {
                $process->finish();

                unset($this->processes[$pid]);
            }
        }

        return $pids;
    }

    /**
     * @throws InvalidQueueDriverException
     */
    private function finishForgottenJob(QueueRecord $queueJob): void
    {
        $queueJob->status = QueueJobStatus::lost;

        $queueJob->pid = null;

        Config::getDriver()->update($queueJob);

        $this->logger->warning('Updated status of lost job with id ' . $queueJob->id);
    }

    private function isJobStillRunning(QueueRecord $queueJob): bool
    {
        if (!$queueJob->pid) {
            return false;
        }

        return Process::runningProcessWithPidExists($queueJob->pid);
    }
}
