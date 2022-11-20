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
    public static function work(): void
    {
        if (self::$process && !self::$process->isRunning()) {
            self::stop();
        }

        if (!self::$process) {
            self::$process = Kernel::ppqCommand('work');

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

            self::$process->stop();

            self::$process = null;
        }
    }
}
