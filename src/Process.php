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

    public static function runningPhpProcessWithPidExists(int $pid): bool
    {
        $ownPid = getmypid();

        if ($ownPid === $pid) {
            return true;
        }

        $process = self::runCommand('ps ax | grep php');

        if ($process->isSuccessful()) {
            foreach (explode(PHP_EOL, $process->getOutput()) as $outputLine) {
                $outputLine = trim($outputLine);

                $splitAtSpace = explode(' ', trim($outputLine), 2);

                if (is_numeric($splitAtSpace[0]) && (int) $splitAtSpace[0] === $pid) {
                    return true;
                }
            }
        }

        if (!$process->isSuccessful() || str_contains($process->getOutput(), 'ps: command not found')) {
            return self::runningPhpProcessWithPidExistsWherePsCommandNotAvailable($pid);
        }

        return false;
    }

    /**
     * @param string|string[] $strings
     * @return bool
     */
    public static function runningPhpProcessContainingStringsExists(string|array $strings): bool
    {
        $ownPid = getmypid();

        if ($ownPid === false) {
            return false;
        }

        $process = self::runCommand('ps ax | grep php');

        if ($process->isSuccessful() && self::processOutputContainsStrings($process, $strings)) {
            foreach (explode(PHP_EOL, $process->getOutput()) as $outputLine) {
                $pid = self::getPidFromPsCommandOutputLine($outputLine);

                if (!$pid || $pid === $ownPid) {
                    continue;
                }

                if (
                    self::stringContainsStrings($outputLine, $strings) &&
                    !self::isOwnPidOrOneOffDuplicate($pid, $outputLine, $strings, $ownPid)
                ) {
                    var_dump('found process: ' . $outputLine);
                    var_dump('own pid: ' . $ownPid . ', process pid: ' . $pid);

                    return true;
                }
            }
        }

        if (!$process->isSuccessful() || str_contains($process->getOutput(), 'ps: command not found')) {
            return self::runningPhpProcessContainingStringsExistsWherePsCommandNotAvailable($strings, $ownPid);
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
        return self::stringContainsStrings($process->getOutput(), $strings);
    }

    /**
     * @param string|string[] $strings
     */
    protected static function stringContainsStrings(string $string, string|array $strings): bool
    {
        if (is_string($strings)) {
            return str_contains($string, $strings);
        }

        foreach ($strings as $stringsString) {
            if (!str_contains($string, $stringsString)) {
                return false;
            }
        }

        return true;
    }

    protected static function runningPhpProcessWithPidExistsWherePsCommandNotAvailable(int $pid): bool
    {
        $process = self::runCommand('cat /proc/' . $pid . '/cmdline');

        return $process->isSuccessful() &&
            !empty($process->getOutput()) &&
            !self::processOutputContainsStrings($process, 'No such file');
    }

    /**
     * @param string|string[] $strings
     */
    protected static function runningPhpProcessContainingStringsExistsWherePsCommandNotAvailable(
        string|array $strings,
        int $ownPid,
    ): bool {
        $process = self::runCommand('cd /proc && ls');

        if ($process->isSuccessful()) {
            foreach (explode(PHP_EOL, $process->getOutput()) as $pid) {
                if (!is_numeric(trim($pid))) {
                    continue;
                }

                $pid = (int) trim($pid);

                if (self::isOwnPidOrOneOffDuplicateWherePsCommandNotAvailable($pid, $strings, $ownPid)) {
                    continue;
                }

                $process = self::runCommand('cat /proc/' . $pid . '/cmdline');

                if ($process->isSuccessful() && self::processOutputContainsStrings($process, $strings)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * I noticed that the list of running processes often contains a duplicate of a process/command with a pid that is
     * one off (-1/+1). When checking for a certain running process we want to exclude the current process we're in.
     * So if a found process's pid is one of the pid of the current PHP process and both (current and one off) match
     * the search request, exclude that process.
     *
     * @param string|string[] $strings
     */
    protected static function isOwnPidOrOneOffDuplicate(
        int $pid,
        string $outputLine,
        string|array $strings,
        int $ownPid
    ): bool {
        if ($pid === $ownPid) {
            return true;
        }

        if ($pid === $ownPid - 1 || $pid === $ownPid + 1) {
            var_dump('one off');

            if (self::stringContainsStrings($outputLine, $strings)) {
                var_dump('contains search strings');

                $process = self::runCommand('ps -q ' . $ownPid);

                var_dump($process->isSuccessful());

                if ($process->isSuccessful()) {
                    var_dump($process->getOutput());

                    var_dump(self::processOutputContainsStrings($process, $strings));
                }

                return $process->isSuccessful() && self::processOutputContainsStrings($process, $strings);
            } else {
                var_dump('doesnt contain search strings');
            }
        }

        return false;
    }

    /**
     * Same as isOwnPidOrOneOffDuplicate() for environments where ps command is not available.
     *
     * @param string|string[] $strings
     */
    protected static function isOwnPidOrOneOffDuplicateWherePsCommandNotAvailable(
        int $pid,
        string|array $strings,
        int $ownPid = null
    ): bool {
        if ($pid === $ownPid) {
            return true;
        }

        if ($pid === $ownPid - 1 || $pid === $ownPid + 1) {
            $process = self::runCommand('cat /proc/' . $ownPid . '/cmdline');

            if ($process->isSuccessful() && self::processOutputContainsStrings($process, $strings)) {
                $process = self::runCommand('cat /proc/' . $pid . '/cmdline');

                return $process->isSuccessful() && self::processOutputContainsStrings($process, $strings);
            }
        }

        return false;
    }

    protected static function getPidFromPsCommandOutputLine(string $line): ?int
    {
        $line = trim($line);

        $splitAtSpace = explode(' ', $line, 2);

        if (!is_numeric($splitAtSpace[0])) {
            return null;
        }

        return (int) $splitAtSpace[0];
    }
}
