<?php

namespace Otsch\Ppq;

use Exception;

class Processes
{
    protected static ?int $ownPid = null;

    protected static ?bool $psCommandAvailable = null;

    /**
     * @var array<int, mixed[]>
     */
    protected static array $processMemory = [];

    /**
     * @param string[] $strings
     * @throws Exception
     */
    public static function processContainingStringsExists(array $strings, bool $excludeOwn = true): bool
    {
        $processes = self::getProcessesContainingStrings($strings, $excludeOwn);

        return !empty($processes);
    }

    /**
     * @param string[] $strings
     * @return array<int, string>
     * @throws Exception
     */
    public static function getProcessesContainingStrings(array $strings, bool $excludeOwn = true): array
    {
        $filtered = [];

        $allCommands = self::getAll($excludeOwn);

        foreach ($allCommands as $pid => $command) {
            foreach ($strings as $string) {
                if (!str_contains($command, $string)) {
                    continue 2;
                }
            }

            $filtered[$pid] = $command;
        }

        return $filtered;
    }

    /**
     * @throws Exception
     */
    public static function pidStillExists(int $pid): bool
    {
        $all = self::getAll(false);

        return isset($all[$pid]);
    }

    /**
     * @param array<int, string>|null $allCommands
     * @throws Exception
     */
    public static function getCommandByPid(int $pid, ?array $allCommands = null): ?string
    {
        $allCommands = $allCommands ?? self::getAll(false);

        return $allCommands[$pid] ?? null;
    }

    /**
     * @throws Exception
     */
    public static function getOwnPid(): int
    {
        if (!self::$ownPid) {
            $pid = getmypid();

            if ($pid === false) {
                throw new Exception("Can't get pid of current process.");
            }

            self::$ownPid = $pid;
        }

        return self::$ownPid;
    }

    /**
     * @return array<string, int|string>|null
     * @throws Exception
     */
    public static function findRunningSubProcess(int $pid, ?string $command = null): ?array
    {
        $all = self::getAll(false);

        $command = $command === null ? self::getCommandByPid($pid, $all) : $command;

        if (!$command) {
            throw new Exception('Can\'t find command for pid');
        }

        for ($i = -2; $i <= 2; $i++) {
            if (isset($all[$pid + $i]) && self::isSubProcessOf($pid + $i, $all[$pid + $i], $pid, $command)) {
                return ['pid' => $pid + $i, 'command' => $all[$pid + $i]];
            }
        }

        return null;
    }

    public static function isSubProcessOf(int $pid, string $command, int $parentPid, string $parentCommand): bool
    {
        return $parentCommand === 'sh -c ' . $command &&
            (
                $pid === $parentPid - 1 ||
                $pid === $parentPid - 2 ||
                $pid === $parentPid + 1 ||
                $pid === $parentPid + 2
            );
    }

    public static function oneIsSubProcessOfTheOther(
        int $pid,
        string $command,
        int $otherPid,
        string $otherCommand
    ): bool {
        return self::isSubProcessOf($pid, $command, $otherPid, $otherCommand) ||
            self::isSubProcessOf($otherPid, $otherCommand, $pid, $command);
    }

    /**
     * @return array<int, string>
     * @throws Exception
     */
    public static function getAll(bool $excludeOwn = true): array
    {
        $processes = self::getProcessesFromPs();

        if ($processes === null) {
            $processes = self::getProcessesFromProcDir();
        }

        if ($excludeOwn === true) {
            $ownPid = self::getOwnPid();

            $ownCommand = self::getCommandByPid($ownPid, $processes);

            $processesExcludingOwn = [];

            foreach ($processes as $pid => $command) {
                if (
                    $pid !== $ownPid &&
                    (!is_string($ownCommand) || !self::oneIsSubProcessOfTheOther($pid, $command, $ownPid, $ownCommand))
                ) {
                    $processesExcludingOwn[$pid] = $command;
                }
            }

            $processes = $processesExcludingOwn;
        }

        return $processes;
    }

    /**
     * @throws Exception
     */
    public static function kill(int $pid): bool
    {
        $command = self::runCommand('kill -9 ' . $pid);

        if (!$command->isSuccessful()) {
            return false;
        }

        if (self::pidStillExists($pid)) {
            Utils::tryUntil(function () use ($pid) {
                return self::pidStillExists($pid) === false;
            }, 10, 50000);
        }

        return self::pidStillExists($pid);
    }

    public static function isZombie(int $pid): bool
    {
        if (self::psCommandAvailable()) {
            $command = self::getCommandByPid($pid);

            var_dump('is zombie?');
            var_dump($command);

            return !$command || str_contains($command, '<defunct>');
        } else {
            $statusCommand = self::runCommand('cat /proc/' . $pid . '/status');

            // In case the process isn't found, let's return true, because this method is called when killing the
            // process didn't work and if it's a zombie process it's just ignored. So when the process now doesn't
            // exist anymore, that's also good.
            if (
                !$statusCommand->isSuccessful() &&
                str_contains($statusCommand->getErrorOutput(), 'No such file or directory')
            ) {
                return true;
            }

            return str_contains($statusCommand->getOutput(), 'Status:' . chr(9) . 'Z (zombie)');
        }
    }

    public static function runCommand(string $command): \Symfony\Component\Process\Process
    {
        $command = \Symfony\Component\Process\Process::fromShellCommandline($command);

        $command->run();

        return $command;
    }

    /**
     * @return array<int, string>|null
     */
    protected static function getProcessesFromPs(): ?array
    {
        $psCommandOutput = self::getPsCommandOutput();

        if ($psCommandOutput === null) {
            return null;
        }

        $processes = [];

        foreach (explode(PHP_EOL, $psCommandOutput) as $lineNumber => $outputLine) {
            $splitAtSpace = explode(' ', trim($outputLine), 2);

            if ($lineNumber === 0) {
                continue;
            }

            if (count($splitAtSpace) === 2 && is_numeric($splitAtSpace[0])) {
                $processes[(int) ($splitAtSpace[0])] = $splitAtSpace[1];
            }
        }

        return $processes;
    }

    protected static function psCommandAvailable(): bool
    {
        if (self::$psCommandAvailable === null) {
            self::getPsCommandOutput();
        }

        /** @var bool self::$psCommandAvailable */

        return self::$psCommandAvailable;
    }

    protected static function getPsCommandOutput(): ?string
    {
        if (self::$psCommandAvailable === false) {
            return null;
        }

        $command = self::runCommand('ps x -o pid,command | grep php');

        if (!$command->isSuccessful() || str_contains($command->getOutput(), 'ps: command not found')) {
            self::$psCommandAvailable = false;

            return null;
        }

        self::$psCommandAvailable = true;

        return $command->getOutput();
    }

    /**
     * @return array<int, string>
     */
    protected static function getProcessesFromProcDir(): array
    {
        $processes = [];

        $process = self::runCommand('cd /proc && ls');

        $now = time();

        if ($process->isSuccessful()) {
            foreach (explode(PHP_EOL, $process->getOutput()) as $pid) {
                if (!is_numeric(trim($pid))) {
                    continue;
                }

                $pid = (int) trim($pid);

                if (isset(self::$processMemory[$pid])) {
                    if ($now - self::$processMemory[$pid]['time'] < 10) {
                        $processes[$pid] = self::$processMemory[$pid]['command'];

                        continue;
                    }

                    unset(self::$processMemory[$pid]);
                }

                $process = self::runCommand('cat /proc/' . $pid . '/cmdline');

                if ($process->isSuccessful()) {
                    $command = self::replaceNullByteChar($process->getOutput());

                    $processes[$pid] = $command;

                    self::$processMemory[$pid] = ['time' => $now, 'command' => $command];
                }
            }
        }

        return $processes;
    }

    protected static function replaceNullByteChar(string $string): string
    {
        return str_replace("\x00", " ", $string);
    }
}
