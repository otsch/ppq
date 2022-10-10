<?php

namespace Otsch\Ppq;

use Exception;

class Signal
{
    protected readonly string $filePath;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $filePath = Config::get('datapath');

        if (!$filePath) {
            throw new Exception('Signal doesn\'t work without a \'datapath\' in config.');
        }

        $this->filePath = $filePath . (!str_ends_with($filePath, '/') ? '/' : '') . 'signal';

        if (!file_exists($this->filePath)) {
            touch($this->filePath);
        }
    }

    public function setStop(): void
    {
        file_put_contents($this->filePath, 'stop');
    }

    public function isStop(): bool
    {
        return file_get_contents($this->filePath) === 'stop';
    }

    public function reset(): void
    {
        file_put_contents($this->filePath, '');
    }
}
