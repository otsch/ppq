<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Exceptions\MissingDataPathException;

class WorkerProcess
{
    public static function isWorking(): bool
    {
        $data = self::get();

        if ($data) {
            if ($data['time'] && (self::getCurrentTime() - $data['time']) > 1000000) {
                return false;
            }

            if (Processes::pidStillExists($data['pid'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     * @throws MissingDataPathException
     */
    public static function set(): void
    {
        if (!Ppq::dataPath()) {
            throw new MissingDataPathException('No datapath defined in config.');
        }

        if (!file_exists(Ppq::dataPath() . 'workerprocess')) {
            touch(Ppq::dataPath() . 'workerprocess');
        }

        $data = serialize(['pid' => Processes::getOwnPid(), 'time' => self::getCurrentTime()]);

        file_put_contents(Ppq::dataPath() . 'workerprocess', $data);
    }

    public static function heartbeat(): void
    {
        if (!Ppq::dataPath()) {
            throw new MissingDataPathException('No datapath defined in config.');
        }

        $fileContent = file_get_contents(Ppq::dataPath() . 'workerprocess');

        if (!$fileContent) {
            $data = [];
        } else {
            $data = unserialize($fileContent);
        }

        if (empty($data['pid'])) {
            $data['pid'] = Processes::getOwnPid();
        }

        $data['time'] = self::getCurrentTime();

        $newFileContent = serialize($data);

        file_put_contents(Ppq::dataPath() . 'workerprocess', $newFileContent);
    }

    /**
     * @return mixed[]|null
     * @throws MissingDataPathException
     */
    public static function get(): ?array
    {
        if (!Ppq::dataPath()) {
            throw new MissingDataPathException('No datapath defined in config.');
        }

        if (file_exists(Ppq::dataPath() . 'workerprocess')) {
            $fileContent = file_get_contents(Ppq::dataPath() . 'workerprocess');

            if (!empty($fileContent)) {
                $data = @unserialize($fileContent);

                if (is_array($data)) {
                    return $data;
                }
            }
        }

        return null;
    }

    public static function getPid(): ?int
    {
        return self::get()['pid'] ?? null;
    }

    public static function getTime(): ?int
    {
        return self::get()['time'] ?? null;
    }

    public static function unset(): void
    {
        if (!Ppq::dataPath()) {
            throw new MissingDataPathException('No datapath defined in config.');
        }

        if (!file_exists(Ppq::dataPath() . 'workerprocess')) {
            touch(Ppq::dataPath() . 'workerprocess');
        }

        file_put_contents(Ppq::dataPath() . 'workerprocess', '');
    }

    protected static function getCurrentTime(): int
    {
        return (int) (microtime(true) * 1000000);
    }
}
