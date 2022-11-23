<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    public function __construct(
        public QueueRecord $queueRecord,
        public SymfonyProcess $process,
        protected LoggerInterface $logger = new EchoLogger(),
    ) {
    }

    /**
     * @throws Exception
     * @throws Exceptions\InvalidQueueDriverException
     */
    public function cancel(): void
    {
        if ($this->process->isRunning()) {
            $this->cancelRunningProcess();
        } else {
            $this->cancelFinishedProcess();
        }

        $this->queueRecord->pid = null;

        Config::getDriver()->update($this->queueRecord);
    }

    public function finish(): void
    {
        $this->reloadQueueRecord();

        if ($this->process->isSuccessful()) {
            $this->queueRecord->status = $status = QueueJobStatus::finished;
        } else {
            $this->queueRecord->status = $status = QueueJobStatus::failed;
        }

        $this->queueRecord->pid = null;

        Config::getDriver()->update($this->queueRecord);

        if ($status === QueueJobStatus::finished) {
            $this->logger->info('Finished job with id ' . $this->queueRecord->id);
        } else {
            $this->logger->error('Job with id ' . $this->queueRecord->id . ' failed');

            if (!empty($this->process->getErrorOutput())) {
                $this->logger->error($this->process->getErrorOutput());
            } elseif (!empty($this->process->getOutput())) {
                $this->logger->debug($this->process->getOutput());
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function cancelRunningProcess(): void
    {
        $pid = $this->process->getPid();

        if ($pid) {
            $command = Processes::getCommandByPid($pid);
        }

        /** @var int $pid */

        $this->process->stop(0);

        if (!empty($command)) {
            $this->tryToFindAndKillZombieSubProcesses($pid, $command);
        }

        $pidIsGone = Utils::tryUntil(function () use ($pid) {
            return Processes::pidStillExists($pid) === false;
        });

        if (!$pidIsGone) {
            if (!Processes::kill($pid)) {
                $this->logger->warning(
                    'Killed running job ' . $this->queueRecord->id . ' (class ' . $this->queueRecord->jobClass .
                    ', args ' . ListCommand::argsToString($this->queueRecord->args) . ')'
                );
            } else {
                throw new Exception('Failed to cancel process.');
            }
        } else {
            $this->logger->warning(
                'Cancelled running job ' . $this->queueRecord->id . ' (class ' . $this->queueRecord->jobClass .
                ', args ' . ListCommand::argsToString($this->queueRecord->args) . ')'
            );
        }

        $this->reloadQueueRecord();

        $this->queueRecord->status = QueueJobStatus::cancelled;
    }

    /**
     * @throws Exception
     */
    protected function cancelFinishedProcess(): void
    {
        $pid = $this->queueRecord->pid;

        if ($pid && Processes::pidStillExists($pid)) {
            $this->tryToFindAndKillZombieSubProcesses($pid);

            $this->process->stop(0);

            if (Processes::pidStillExists($pid)) {
                if (Processes::kill($pid)) {
                    $this->logger->warning('Killed job ' . $this->queueRecord->id);
                } else {
                    $this->logger->error('Killing job ' . $this->queueRecord->id . ' failed');

                    throw new Exception('Killing job failed.');
                }
            } else {
                $this->logger->warning('Stopped job ' . $this->queueRecord->id);
            }
        } else {
            $this->logger->info(
                'Job ' . $this->queueRecord->id . ' should have been cancelled, but it looks like it already finished.'
            );
        }

        $this->reloadQueueRecord();

        $this->queueRecord->status = QueueJobStatus::finished;
    }

    protected function tryToFindAndKillZombieSubProcesses(int $pid, ?string $command = null): void
    {
        $zombieSubProcess = Processes::findRunningSubProcess($pid, $command);

        if ($zombieSubProcess && is_int($zombieSubProcess['pid'])) {
            Processes::kill($zombieSubProcess['pid']);
        }
    }

    protected function reloadQueueRecord(): void
    {
        $updatedQueueRecord = Ppq::find($this->queueRecord->id);

        if ($updatedQueueRecord) {
            $this->queueRecord = $updatedQueueRecord;
        }
    }
}
