<?php

namespace Otsch\Ppq;

use Otsch\Ppq\Contracts\QueueEventListener;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Loggers\EchoLogger;

class QueueEventListeners
{
    /**
     * @var array<string, array<int, string|QueueEventListener>> $listeners
     */
    protected array $listeners = [];

    /**
     * @param array<string, string|QueueEventListener|array<int, string|QueueEventListener>> $listeners
     */
    public function __construct(array $listeners = [])
    {
        foreach ($listeners as $type => $listenerClasses) {
            if (!in_array($type, ['waiting', 'running', 'finished', 'failed', 'lost', 'cancelled'], true)) {
                continue;
            }

            $this->listeners[$type] = is_array($listenerClasses) ? $listenerClasses : [$listenerClasses];
        }
    }

    public function callWaiting(QueueRecord $queueRecord): void
    {
        $this->call('waiting', $queueRecord);
    }

    public function callRunning(QueueRecord $queueRecord): void
    {
        $this->call('running', $queueRecord);
    }

    public function callFinished(QueueRecord $queueRecord): void
    {
        $this->call('finished', $queueRecord);
    }

    public function callFailed(QueueRecord $queueRecord): void
    {
        $this->call('failed', $queueRecord);
    }

    public function callLost(QueueRecord $queueRecord): void
    {
        $this->call('lost', $queueRecord);
    }

    public function callCancelled(QueueRecord $queueRecord): void
    {
        $this->call('cancelled', $queueRecord);
    }

    protected function call(string $type, QueueRecord $queueRecord): void
    {
        if (isset($this->listeners[$type])) {
            foreach ($this->listeners[$type] as $key => $listener) {
                if (is_string($listener) && class_exists($listener)) {
                    $instance = new $listener();

                    if ($instance instanceof QueueEventListener) {
                        $this->listeners[$type][$key] = $instance;
                    } else {
                        (new EchoLogger())->warning(
                            'Queue Listener ' . $listener . ' does not implement the QueueEventListener interface.'
                        );
                    }
                }

                if ($this->listeners[$type][$key] instanceof QueueEventListener) {
                    $this->listeners[$type][$key]->invoke($queueRecord);
                }
            }
        }
    }
}
