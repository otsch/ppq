<?php

namespace Otsch\Ppq;

use Exception;

class Signal
{
    protected ?string $filePath = null;

    /**
     * @throws Exception
     */
    public function setStop(): void
    {
        file_put_contents($this->filePath(), 'stop');
    }

    /**
     * @throws Exception
     */
    public function isStop(): bool
    {
        return file_get_contents($this->filePath()) === 'stop';
    }

    /**
     * @throws Exception
     */
    public function reset(): void
    {
        file_put_contents($this->filePath(), '');
    }

    /**
     * @throws Exception
     */
    private function filePath(): string
    {
        if (!$this->filePath) {
            $filePath = Config::get('datapath');

            if (!$filePath) {
                throw new Exception('Signal doesn\'t work without a \'datapath\' in config.');
            }

            $this->filePath = $filePath . (!str_ends_with($filePath, '/') ? '/' : '') . 'signal';

            if (!file_exists($this->filePath)) {
                touch($this->filePath);
            }
        }

        return $this->filePath;
    }
}
