<?php

namespace Integration;

use Exception;
use Otsch\Ppq\Kernel;
use Symfony\Component\Process\Process;

class WorkerProcess
{
    public static ?Process $process = null;

    /**
     * @return void
     * @throws Exception
     */
    public static function work(string $startingTest = ''): void
    {
        if (self::$process && !self::$process->isRunning()) {
            self::stop();
        }

        if (!self::$process) {
            self::$process = Kernel::ppqCommand('work --startingtest=' . $startingTest);

            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

            var_dump('start worker process');

            self::$process->start();

            usleep(50000);

            if (!self::$process->isRunning()) {
                if (self::$process->isSuccessful()) {
                    throw new Exception(
                        'Looks like worker process immediately stopped. Output: ' . self::$process->getOutput()
                    );
                } else {
                    throw new Exception(
                        'Looks like worker process immediately died. Error output: ' . self::$process->getErrorOutput()
                    );
                }
            }
        }
    }

    public static function stop(): void
    {
        if (self::$process) {
            var_dump('stop worker process');

            $exitCode = self::$process->stop(0);

            var_dump($exitCode);

            var_dump(self::$process->getOutput());

            self::$process = null;

            usleep(30000);
        }
    }
}
