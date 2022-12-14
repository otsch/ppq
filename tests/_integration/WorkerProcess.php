<?php

namespace Integration;

use Exception;
use Otsch\Ppq\Exceptions\MissingDataPathException;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Loggers\EchoLogger;
use Otsch\Ppq\Processes;
use Otsch\Ppq\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class WorkerProcess
{
    public static ?Process $process = null;

    public static ?int $pid = null;

    public static ?string $processCommand = null;

    protected static ?LoggerInterface $logger = null;

    protected static function logger(): LoggerInterface
    {
        if (!self::$logger) {
            self::$logger = new EchoLogger();
        }

        return self::$logger;
    }

    /**
     * @return void
     * @throws Exception
     * @throws MissingDataPathException
     */
    public static function work(): void
    {
        if (self::$process && !self::$process->isRunning()) {
            self::stop();
        }

        if (!self::$process) {
            if (\Otsch\Ppq\WorkerProcess::isWorking()) {
                throw new Exception('Worker already working: ' . (\Otsch\Ppq\WorkerProcess::getPid() ?? 'unknown pid'));
            }

            self::$process = Kernel::ppqCommand('work');

            self::$process->start();

            $isWorking = Utils::tryUntil(function () {
                return \Otsch\Ppq\WorkerProcess::isWorking();
            });

            if (!$isWorking) {
                throw new Exception('Worker process immediately died: ' . self::$process->getOutput());
            }

            self::$pid = self::$process->getPid();

            self::logger()->info('Started worker process ' . self::$pid);

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

                if (is_int(self::$pid)) {
                    Utils::tryUntil(function () {
                        return is_int(self::$pid) && Processes::pidStillExists(self::$pid) === false;
                    });

                    if (Processes::pidStillExists(self::$pid)) {
                        throw new Exception('Stopping worker process failed');
                    }
                }
            } else {
                if (is_int(self::$pid) && Processes::pidStillExists(self::$pid)) {
                    self::$process->stop(0);

                    Utils::tryUntil(function () {
                        return is_int(self::$pid) && Processes::pidStillExists(self::$pid) === false;
                    });

                    if (Processes::pidStillExists(self::$pid)) { // @phpstan-ignore-line
                        throw new Exception('Stopping worker process failed');
                    }
                }
            }

            if (\Otsch\Ppq\WorkerProcess::isWorking()) {
                self::tryToFindAndKillWorkerSubProcessZombie();
            }

            self::resetProcessData();
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
            return;
        }

        $otherWorkerCommand = Processes::getCommandByPid($otherWorkerPid);

        if (!$otherWorkerCommand) {
            return;
        }

        if (
            Processes::isSubProcessOf(
                $otherWorkerPid,
                $otherWorkerCommand,
                self::$pid, // @phpstan-ignore-line
                self::$processCommand // @phpstan-ignore-line
            )
        ) {
            $stoppedWorking = Utils::tryUntil(function () {
                return \Otsch\Ppq\WorkerProcess::isWorking() === false;
            });

            if (!$stoppedWorking) {
                if (Processes::kill($otherWorkerPid) || Processes::isZombie($otherWorkerPid)) {
                    \Otsch\Ppq\WorkerProcess::unset();
                } else {
                    throw new Exception('Failed to kill worker sub-process ' . $otherWorkerPid);
                }
            }
        } elseif (Processes::isZombie($otherWorkerPid)) {
            \Otsch\Ppq\WorkerProcess::unset();
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
