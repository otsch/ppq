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
        public SymfonyProcess $process,
        protected LoggerInterface $logger = new EchoLogger(),
    ) {
    }

    /**
     * @param string|string[] $strings
     * @return bool
     */
    public static function runningProcessContainingStringsExists(string|array $strings): bool
    {
        $ownPid = getmypid();

        if ($ownPid === false) {
            return false;
        }

        $process = self::runCommand('ps aux | grep ppq');

        if ($process->isSuccessful() && self::processOutputContainsStrings($process, $strings)) {
            foreach (explode(PHP_EOL, $process->getOutput()) as $outputLine) {
                if (str_contains($outputLine, 'vendor/bin/ppq work') && !str_contains($outputLine, (string) $ownPid)) {
                    return true;
                }
            }
        }

        if (!$process->isSuccessful() || str_contains($process->getOutput(), 'ps: command not found')) {
            $process = self::runCommand('cd /proc && ls');

            if ($process->isSuccessful()) {
                foreach (explode(PHP_EOL, $process->getOutput()) as $pid) {
                    $pid = trim($pid);

                    if (!is_numeric($pid) || (int) $pid === $ownPid) {
                        continue;
                    }

                    $process = self::runCommand('cat /proc/' . $pid . '/cmdline');

                    if ($process->isSuccessful() && self::processOutputContainsStrings($process, $strings)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function finish(): void
    {
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

    protected static function runCommand(string $command): SymfonyProcess
    {
        $process = SymfonyProcess::fromShellCommandline($command);

        $process->run();

        return $process;
    }

    /**
     * @param string|string[] $strings
     */
    protected static function processOutputContainsStrings(SymfonyProcess $process, string|array $strings): bool
    {
        $output = $process->getOutput();

        if (is_string($strings)) {
            return str_contains($output, $strings);
        }

        foreach ($strings as $string) {
            if (!str_contains($output, $string)) {
                return false;
            }
        }

        return true;
    }
}
