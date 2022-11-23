<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Entities\QueueRecord;

class Logs
{
    /**
     * @throws Exception
     */
    public static function getJobLog(QueueRecord $queueRecord, ?int $numberOfLines = 1000): string
    {
        $jobLogFilePath = self::queueJobLogPath($queueRecord);

        if (!$numberOfLines) {
            $fileContent = file_get_contents($jobLogFilePath);

            return $fileContent === false ? '' : $fileContent;
        }

        return implode('', self::getLastXLinesOfFile($jobLogFilePath, $numberOfLines));
    }

    /**
     * @param QueueRecord $queueRecord
     * @param int|null $numberOfLines
     * @return void
     * @throws Exception
     */
    public static function printJobLog(QueueRecord $queueRecord, ?int $numberOfLines = 1000): void
    {
        echo self::getJobLog($queueRecord, $numberOfLines);
    }

    public static function logPath(): string
    {
        $logPath = Ppq::dataPath() . 'logs';

        if (!file_exists($logPath)) {
            mkdir($logPath);
        }

        return $logPath;
    }

    public static function queueLogPath(string $queue): string
    {
        $path = self::logPath() . '/' . $queue;

        if (!file_exists($path)) {
            mkdir($path);
        }

        return $path;
    }

    public static function queueJobLogPath(QueueRecord $queueRecord): string
    {
        return self::queueLogPath($queueRecord->queue) . '/' . $queueRecord->id . '.log';
    }

    public static function forget(QueueRecord $queueRecord): void
    {
        $path = self::queueLogPath($queueRecord->queue) . '/' . $queueRecord->id . '.log';

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @return string[]
     * @throws Exception
     */
    protected static function getLastXLinesOfFile(string $path, int $numberOfLines): array
    {
        $handle = fopen($path, "r");

        if (!$handle) {
            throw new Exception("Can't open log file.");
        }

        $lines = [];

        while (!feof($handle)) {
            $line = fgets($handle);

            if (is_string($line)) {
                $lines[] = $line;
            }

            if (count($lines) > $numberOfLines) {
                array_shift($lines);
            }
        }

        fclose($handle);

        return $lines;
    }
}
