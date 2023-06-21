<?php

namespace Otsch\Ppq;

class Argv
{
    /**
     * @param string[] $argv
     */
    public function __construct(private array $argv)
    {
    }

    /**
     * @param string[] $argv
     */
    public static function make(array $argv): self
    {
        return new self($argv);
    }

    public function workQueues(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'work';
    }

    public function stopQueues(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'stop';
    }

    public function clearQueue(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'clear';
    }

    public function clearAllQueues(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'clear-all';
    }

    public function flushQueue(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'flush';
    }

    public function flushAllQueues(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'flush-all';
    }

    public function subjectQueue(): ?string
    {
        return $this->argv[2] ?? null;
    }

    public function runJob(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'run';
    }

    public function cancelJob(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'cancel';
    }

    public function checkSchedule(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'check-schedule';
    }

    public function list(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'list';
    }

    public function logs(): bool
    {
        return isset($this->argv[1]) && $this->argv[1] === 'logs';
    }

    public function lines(): ?string
    {
        return $this->getArgValueByKey('lines');
    }

    public function jobId(): ?string
    {
        return $this->argv[2] ?? null;
    }

    public function configPath(): ?string
    {
        return $this->getArgValueByKey('config');
    }

    protected function getArgValueByKey(string $key): ?string
    {
        foreach ($this->argv as $arg) {
            if (!empty($arg) && str_starts_with($arg, '--' . $key . '=')) {
                return substr($arg, strlen($key) + 3);
            }
        }

        return null;
    }
}
