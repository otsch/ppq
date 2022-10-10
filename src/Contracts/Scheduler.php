<?php

namespace Otsch\Ppq\Contracts;

interface Scheduler
{
    public function checkScheduleAndQueue(): void;
}
