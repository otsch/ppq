<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
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

    public function hasAvailableSlot(): bool
    {
        $count = $this->runningProcessesCount();

        return $count < $this->concurrentJobs;
    }

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

    public function clearRunningJobs(): void
    {
        foreach ($this->getForgottenRunningJobs() as $forgottenRunningJob) {
            if (!$this->isJobStillRunning($forgottenRunningJob)) {
                $this->finishForgottenJob($forgottenRunningJob);
            }
        }
    }

    public function runningProcessesCount(): int
    {
        $forgottenRunningJobs = $this->getForgottenRunningJobs();

        if (count($forgottenRunningJobs) > 0) {
            $stillRunningForgottenJobs = 0;

            foreach ($forgottenRunningJobs as $forgottenRunningJob) {
                if ($this->isJobStillRunning($forgottenRunningJob)) {
                    $stillRunningForgottenJobs++;
                } else {
                    $this->finishForgottenJob($forgottenRunningJob);
                }
            }
        }

        return count($forgottenRunningJobs) + count($this->processes);
    }

    /**
     * @return QueueRecord[]
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

        $process = SymfonyProcess::fromShellCommandline('cat /proc/' . $queueJob->pid . '/cmdline');

        $process->run();

        if (!$process->isSuccessful()) {
            return false;
        }

        return true;
    }
}
