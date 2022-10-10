<?php

namespace Otsch\Ppq\Loggers;

use DateTime;
use Psr\Log\LoggerInterface;
use Stringable;
use UnexpectedValueException;

class FileLogger implements LoggerInterface
{
    public function __construct(protected string $filePath = '')
    {
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], true)) {
            throw new UnexpectedValueException('Unknown log level.');
        }

        file_put_contents($this->filePath, $this->getTimeAndLevel($level) . (string) $message . PHP_EOL, FILE_APPEND);
    }

    private function getTimeAndLevel(string $level): string
    {
        return $this->time() . " [" . strtoupper($level) . "] ";
    }

    private function time(): string
    {
        return (new DateTime())->format('H:i:s:u');
    }
}
