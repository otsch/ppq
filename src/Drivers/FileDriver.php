<?php

namespace Otsch\Ppq\Drivers;

use Exception;
use Otsch\Ppq\Config;
use Otsch\Ppq\Contracts\QueueDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

class FileDriver implements QueueDriver
{
    protected readonly string $basePath;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $basePath = Config::get('datapath');

        if (!$basePath) {
            throw new Exception('FileDriver doesn\'t work without defining a datapath in config.');
        }

        $this->basePath = $basePath;
    }

    /**
     * @throws Exception
     */
    public function add(QueueRecord $queueRecord): void
    {
        $queue = $this->getQueue($queueRecord->queue);

        $queue[$queueRecord->id] = $queueRecord;

        $this->saveQueue($queueRecord->queue, $queue);

        $this->addIdToIndex($queueRecord);
    }

    /**
     * @throws Exception
     */
    public function update(QueueRecord $queueRecord): void
    {
        $queue = $this->getQueue($queueRecord->queue);

        $queue[$queueRecord->id] = $queueRecord;

        $this->saveQueue($queueRecord->queue, $queue);
    }

    /**
     * @throws Exception
     */
    public function get(string $id): ?QueueRecord
    {
        $index = $this->getIndex();

        if (!isset($index[$id])) {
            return null;
        }

        $queue = $this->getQueue($index[$id]);

        return $queue[$id] ?? null;
    }

    public function forget(string $id): void
    {
        $index = $this->getIndex();

        if (!isset($index[$id])) {
            return;
        }

        $this->forgetFromQueue($index[$id], $id);

        unset($index[$id]);

        $this->saveIndex($index);
    }

    /**
     * @param mixed[]|null $args
     * @return QueueRecord[]
     * @throws Exception
     */
    public function where(
        string $queue,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): array {
        $queueRecords = $this->getQueue($queue);

        $filtered = [];

        foreach ($queueRecords as $id => $queueRecord) {
            if ($this->matchesFilters($queueRecord, $jobClassName, $status, $args, $pid)) {
                $filtered[$id] = $queueRecord;
            }
        }

        return $filtered;
    }

    /**
     * @param string $queue
     * @return array<string, QueueRecord>
     * @throws Exception
     */
    protected function getQueue(string $queue): array
    {
        if (!file_exists($this->basePath('queue-' . $queue))) {
            touch($this->basePath('queue-' . $queue));

            $this->saveQueue($queue, []);

            return [];
        }

        $queueJobs = [];

        foreach ($this->getUnserializedQueueContent($queue) as $id => $queueJobData) {
            $queueJobs[$id] = QueueRecord::fromArray($queueJobData);
        }

        return $queueJobs;
    }

    /**
     * @param QueueRecord[] $queueData
     */
    protected function saveQueue(string $queue, array $queueData): void
    {
        foreach ($queueData as $queueJobId => $queueJob) {
            if ($queueJob instanceof QueueRecord) {
                $queueData[$queueJobId] = $queueJob->toArray();
            }
        }

        file_put_contents($this->basePath('queue-' . $queue), serialize($queueData));
    }

    /**
     * @throws Exception
     */
    protected function forgetFromQueue(string $queue, string $id): void
    {
        $queueData = $this->getQueue($queue);

        if (isset($queueData[$id])) {
            unset($queueData[$id]);
        }

        $this->saveQueue($queue, $queueData);
    }

    protected function addIdToIndex(QueueRecord $record): void
    {
        $index = $this->getIndex();

        $index[$record->id] = $record->queue;

        $this->saveIndex($index);
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    protected function getIndex(): array
    {
        if (!file_exists($this->basePath('index'))) {
            touch($this->basePath('index'));

            $this->saveIndex([]);
        }

        return $this->getUnserializedFileContent($this->basePath('index'));
    }

    /**
     * @return mixed[]
     * @throws Exception
     */
    protected function getUnserializedQueueContent(string $queue): array
    {
        return $this->getUnserializedFileContent($this->basePath('queue-' . $queue));
    }

    /**
     * There can be problems when read and write happen exactly at the same time. In this case it can happen that
     * unserialize fails, because the file is currently being written. Just wait a little and try again.
     *
     * @throws Exception
     */
    protected function getUnserializedFileContent(string $filepath): mixed
    {
        $tries = 0;

        while ($tries < 100) {
            $fileContent = file_get_contents($filepath);

            if ($fileContent !== false) {
                $content = @unserialize($fileContent);

                if ($content !== false) {
                    return $content;
                }
            }

            usleep(100);

            $tries++;
        }

        throw new Exception('Can\'t read file ' . $filepath);
    }

    /**
     * @param array<string, string> $index
     */
    protected function saveIndex(array $index): void
    {
        file_put_contents($this->basePath('index'), serialize($index));
    }

    /**
     * @param mixed[]|null $args
     */
    protected function matchesFilters(
        QueueRecord $record,
        ?string $jobClassName = null,
        ?QueueJobStatus $status = QueueJobStatus::waiting,
        ?array $args = null,
        ?int $pid = null,
    ): bool {
        if ($jobClassName !== null && $record->jobClass !== $jobClassName) {
            return false;
        }

        if ($status !== null && $record->status !== $status) {
            return false;
        }

        if ($args !== null && !$this->matchesArgFilters($record, $args)) {
            return false;
        }

        if ($pid !== null && $pid !== $record->pid) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed[] $matchArgs
     */
    protected function matchesArgFilters(QueueRecord $record, array $matchArgs): bool
    {
        foreach ($matchArgs as $key => $value) {
            if (!isset($record->args[$key]) || $record->args[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    protected function basePath(string $fileName): string
    {
        return $this->basePath . (!str_ends_with($this->basePath, '/') ? '/' : '') . $fileName;
    }
}
