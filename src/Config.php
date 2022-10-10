<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Contracts\QueueDriver;

class Config
{
    /**
     * @var mixed[]
     */
    protected static array $configData = [];

    protected static string $path = __DIR__ . '/../../../../config/ppq.php';

    public static function setPath(string $path): void
    {
        self::$path = $path;
    }

    public static function getDriver(): QueueDriver
    {
        return self::get('driver');
    }

    public static function get(string $key = ''): mixed
    {
        $data = self::getConfigData();

        return $data[$key] ?? null;
    }

    /**
     * @return mixed[]
     */
    public static function all(): array
    {
        return self::getConfigData();
    }

    /**
     * @return mixed[]
     */
    protected static function getConfigData(): array
    {
        if (empty(self::$configData[self::$path])) {
            self::$configData[self::$path] = require(self::$path);
        }

        return self::$configData[self::$path];
    }
}
