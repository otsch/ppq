<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Exceptions\InvalidQueueDriverException;

class Config
{
    /**
     * @var mixed[]
     */
    protected static array $configData = [];

    protected static string $path = __DIR__ . '/../../../../config/ppq.php';

    protected static ?QueueDriver $driverInstance = null;

    public static function setPath(string $path): void
    {
        if ($path !== self::$path) {
            self::$path = $path;

            self::$driverInstance = null;
        }
    }

    public static function getPath(): string
    {
        return self::$path;
    }

    /**
     * @throws InvalidQueueDriverException
     */
    public static function getDriver(): QueueDriver
    {
        if (!self::$driverInstance) {
            $driverClassName = self::get('driver') ?? FileDriver::class;

            $driver = new $driverClassName();

            if (!$driver instanceof QueueDriver) {
                throw new InvalidQueueDriverException(
                    'Configured driver must be an implementation of the QueueDriver interface.'
                );
            }

            self::$driverInstance = $driver;
        }

        return self::$driverInstance;
    }

    /**
     * @return Queue[]
     */
    public static function getQueues(): array
    {
        $queues = self::get('queues') ?? [];

        foreach ($queues as $queueName => $queueConfig) {
            $queues[$queueName] = new Queue(
                $queueName,
                $queueConfig['concurrent_jobs'] ?? 2,
                $queueConfig['keep_last_x_past_jobs'] ?? 100,
            );
        }

        return $queues;
    }

    /**
     * @return string[]
     */
    public static function getQueueNames(): array
    {
        $queues = self::get('queues') ?? [];

        /** @var array<string, mixed[]> $queues */

        return array_keys($queues);
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
