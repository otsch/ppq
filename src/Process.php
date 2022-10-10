<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    public function __construct(
        public QueueRecord $queueRecord,
        public ?SymfonyProcess $process = null,
        protected LoggerInterface $logger = new EchoLogger(),
    ) {
    }

    public function finish(): void
    {
        if ($this->process) {
            if ($this->process->isRunning()) {
                $this->process->stop();
            }

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
    }
}
