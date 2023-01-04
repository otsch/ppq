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
     * @throws Exceptions\InvalidQueueDriverException
     */
    public function cancel(QueueEventListeners $eventListeners): void
    {
        try {
            if ($this->process->isRunning()) {
                $this->cancelRunningProcess();
            } else {
                $this->cancelFinishedProcess();
            }

            $this->queueRecord->pid = null;

            $this->queueRecord->setDoneNow();

            Config::getDriver()->update($this->queueRecord);

            $eventListeners->callCancelled($this->queueRecord);
        } catch (Exception $exception) {
            $this->reloadQueueRecord();

            $this->queueRecord->status = QueueJobStatus::running;

            Config::getDriver()->update($this->queueRecord);
        }
    }

    public function finish(QueueEventListeners $eventListeners): void
    {
        $this->reloadQueueRecord();

        if ($this->process->isSuccessful()) {
            $this->queueRecord->status = $status = QueueJobStatus::finished;
        } else {
            $this->queueRecord->status = $status = QueueJobStatus::failed;
        }

        $this->queueRecord->pid = null;

        $this->queueRecord->setDoneNow();

        Config::getDriver()->update($this->queueRecord);

        if ($status === QueueJobStatus::finished) {
            $eventListeners->callFinished($this->queueRecord);

            $this->logger->info('Finished job with id ' . $this->queueRecord->id);
        } else {
            $eventListeners->callFailed($this->queueRecord);

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
        } elseif (!$this->process->isRunning()) {
            $this->logger->info(
                'Job ' . $this->queueRecord->id . ' should have been cancelled, but it looks like it already finished.'
            );

            return;
        } else {
            $this->logger->warning(
                'Can\'t get pid of running process about to being cancelled to look for running sub processes.'
            );

            return;
        }

        $this->process->stop(0);

        $pidIsGone = Utils::tryUntil(function () use ($pid) {
            return Processes::pidStillExists($pid) === false;
        });

        if (!$pidIsGone) {
            if (Processes::kill($pid)) {
                $this->logger->warning('Killed running job by pid ' . $this->queueRecord->pid);
            } else {
                $this->logger->error('Failed to cancel process');

                throw new Exception('Failed to cancel process.');
            }
        }

        if (!empty($command)) {
            $this->tryToFindAndKillRunningSubProcesses($pid, $command);
        } else {
            $this->logger->warning('Can\'t get command of cancelled process to look for running sub processes.');
        }

        $this->logger->warning(
            'Cancelled running job ' . $this->queueRecord->id . ' (class ' . $this->queueRecord->jobClass .
            ', args ' . ListCommand::argsToString($this->queueRecord->args) . ')'
        );

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
            $this->process->stop(0);

            if (Processes::pidStillExists($pid)) { // @phpstan-ignore-line
                if (Processes::kill($pid)) {
                    $this->logger->warning(
                        'Killed pid ' . $pid . ' of job ' . $this->queueRecord->id . ' (class ' .
                        $this->queueRecord->jobClass . ', args ' . ListCommand::argsToString($this->queueRecord->args) .
                        ')'
                    );
                } else {
                    $this->logger->error('Killing job ' . $this->queueRecord->id . ' failed');

                    throw new Exception('Killing job failed.');
                }
            } else {
                $this->logger->warning(
                    'Stopped job ' . $this->queueRecord->id . ' (class ' .
                    $this->queueRecord->jobClass . ', args ' . ListCommand::argsToString($this->queueRecord->args) .
                    ')'
                );
            }

            $this->tryToFindAndKillRunningSubProcesses($pid);
        } else {
            $this->logger->info(
                'Job ' . $this->queueRecord->id . ' should have been cancelled, but it looks like it already finished.'
            );
        }

        $this->reloadQueueRecord();

        $this->queueRecord->status = QueueJobStatus::finished;
    }

    protected function tryToFindAndKillRunningSubProcesses(int $pid, ?string $command = null): void
    {
        $zombieSubProcess = Processes::findRunningSubProcess($pid, $command);

        if ($zombieSubProcess && is_int($zombieSubProcess['pid'])) {
            if (Processes::kill($zombieSubProcess['pid'])) {
                $this->logger->warning('Killed still running sub-process ' . $zombieSubProcess['pid']);
            } else {
                throw new Exception('Failed to kill still running sub-process');
            }
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
