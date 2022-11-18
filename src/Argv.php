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

    public function jobId(): ?string
    {
        return $this->argv[2] ?? null;
    }

    public function configPath(): ?string
    {
        $lastArg = end($this->argv);

        if (!empty($lastArg) && str_starts_with($lastArg, '--c=')) {
            return substr($lastArg, 4);
        }

        return null;
    }
}
