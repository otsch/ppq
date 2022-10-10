<?php

namespace Otsch\Ppq\Contracts;

interface QueueableJob
{
    public function invoke(): void;
}
