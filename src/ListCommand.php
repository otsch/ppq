<?php

namespace Otsch\Ppq;

class ListCommand
{
    /**
     * @throws Exceptions\InvalidQueueDriverException
     */
    public function list(): void
    {
        echo PHP_EOL;

        foreach (Ppq::queueNames() as $queueName) {
            echo "Queue: " . $queueName . PHP_EOL;

            foreach (Ppq::running($queueName) as $queueRecord) {
                echo 'id: ' . $queueRecord->id . ', jobClass: ' . $queueRecord->jobClass . ' - running' . PHP_EOL;
            }

            foreach (Ppq::waiting($queueName) as $queueRecord) {
                echo 'id: ' . $queueRecord->id . ', jobClass: ' . $queueRecord->jobClass . ' - waiting' . PHP_EOL;
            }

            echo PHP_EOL;
        }
    }
}
