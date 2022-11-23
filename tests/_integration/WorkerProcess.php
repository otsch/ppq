<?php

namespace Integration;

use Exception;
use Otsch\Ppq\Exceptions\MissingDataPathException;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Processes;
use Otsch\Ppq\Utils;
use Symfony\Component\Process\Process;

class WorkerProcess
{
    public static ?Process $process = null;

    public static ?int $pid = null;

    public static ?string $processCommand = null;

    /**
     * @param string $startingTest
     * @return void
     * @throws Exception
     * @throws MissingDataPathException
     */
    public static function work(string $startingTest = ''): void
    {
        if (self::$process && !self::$process->isRunning()) {
            self::stop();
        }

        if (!self::$process) {
            if (\Otsch\Ppq\WorkerProcess::isWorking()) {
                throw new Exception('Worker already working: ' . (\Otsch\Ppq\WorkerProcess::getPid() ?? 'unknown pid'));
            }

            self::$process = Kernel::ppqCommand('work --startingtest=' . $startingTest);

            self::$process->start();

            $isWorking = Utils::tryUntil(function () {
                return \Otsch\Ppq\WorkerProcess::isWorking();
            });

            if (!$isWorking) {
                throw new Exception('Worker process immediately died: ' . self::$process->getOutput());
            }

            self::$pid = self::$process->getPid();

            if (is_int(self::$pid)) {
                self::$processCommand = Processes::getCommandByPid(self::$pid);
            }
        }
    }

    /**
     * @throws Exception
     * @throws MissingDataPathException
     */
    public static function stop(): void
    {
        if (self::$process) {
            if (!self::$process->isRunning()) {
                self::$process->stop(0);

                if (\Otsch\Ppq\WorkerProcess::isWorking()) {
                    self::tryToFindAndKillWorkerSubProcessZombie();
                }

                self::resetProcessData();
            } else {
                self::$process->stop(0);

                if (\Otsch\Ppq\WorkerProcess::isWorking()) {
                    self::tryToFindAndKillWorkerSubProcessZombie();
                }

                self::resetProcessData();
            }
        }
    }

    /**
     * @throws Exception
     * @throws MissingDataPathException
     */
    protected static function tryToFindAndKillWorkerSubProcessZombie(): void
    {
        $otherWorkerPid = \Otsch\Ppq\WorkerProcess::getPid();

        if (!$otherWorkerPid) {
            throw new Exception('WorkerProcess thinks that a worker is running but has no pid.');
        }

        $otherWorkerCommand = Processes::getCommandByPid($otherWorkerPid);

        if (!$otherWorkerCommand) {
            throw new Exception('Can\'t get command of other worker process.');
        }

        if (
            Processes::isSubProcessOf(
                $otherWorkerPid,
                $otherWorkerCommand,
                self::$pid, // @phpstan-ignore-line
                self::$processCommand // @phpstan-ignore-line
            )
        ) {
            if (Processes::kill($otherWorkerPid)) {
                \Otsch\Ppq\WorkerProcess::unset();
            } else {
                throw new Exception('Failed to kill worker sub-process ' . $otherWorkerPid);
            }
        } else {
            throw new Exception('Untracked worker process running: ' . $otherWorkerPid . ' - ' . $otherWorkerCommand);
        }
    }

    protected static function resetProcessData(): void
    {
        self::$process = null;

        self::$pid = null;

        self::$processCommand = null;
    }
}
