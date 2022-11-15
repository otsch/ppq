<?php

namespace Otsch\Ppq;

class Fail
{
    public function withMessage(string $message): void
    {
        error_log($message);

        exit(1);
    }
}
