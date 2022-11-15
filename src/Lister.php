<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Entities\Values\QueueJobStatus;

class Lister
{
    public function list(): void
    {
        $driver = Config::getDriver();

        echo PHP_EOL;

        foreach (Config::getQueues() as $queue) {
            echo "Queue: " . $queue->name . PHP_EOL;

            foreach ($driver->where($queue->name, status: QueueJobStatus::running) as $queueRecord) {
                echo 'id: ' . $queueRecord->id . ', jobClass: ' . $queueRecord->jobClass . ' - running' . PHP_EOL;
            }

            foreach ($driver->where($queue->name, status: QueueJobStatus::waiting) as $queueRecord) {
                echo 'id: ' . $queueRecord->id . ', jobClass: ' . $queueRecord->jobClass . ' - waiting' . PHP_EOL;
            }

            echo PHP_EOL;
        }
    }
}
