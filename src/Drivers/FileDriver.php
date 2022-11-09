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
        [$queueFileHandle, $indexFileHandle] = $this->openAndLockQueueAndIndexFiles($queueRecord->queue);

        $queue = $this->getQueue($queueRecord->queue, $queueFileHandle);

        $queue[$queueRecord->id] = $queueRecord;

        $this->saveQueue($queue, $queueFileHandle);

        $this->addIdToIndex($queueRecord, $indexFileHandle);

        $this->releaseLockAndCloseHandle($queueFileHandle);

        $this->releaseLockAndCloseHandle($indexFileHandle);
    }

    /**
     * @throws Exception
     */
    public function update(QueueRecord $queueRecord): void
    {
        $queueFileHandle = $this->openAndLockQueueFile($queueRecord->queue);

        $queue = $this->getQueue($queueRecord->queue, $queueFileHandle);

        if (isset($queue[$queueRecord->id])) {
            $queue[$queueRecord->id] = $queueRecord;
        }

        $this->saveQueue($queue, $queueFileHandle);

        $this->releaseLockAndCloseHandle($queueFileHandle);
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

    /**
     * @throws Exception
     */
    public function forget(string $id): void
    {
        $indexFileHandle = $this->openAndLockIndexFile();

        $index = $this->getIndex($indexFileHandle);

        if (!isset($index[$id])) {
            return;
        }

        $queue = $index[$id];

        $queueFileHandle = $this->openAndLockQueueFile($queue);

        $this->forgetFromQueue($queue, $id, $queueFileHandle, );

        unset($index[$id]);

        $this->saveIndex($index, $indexFileHandle);

        $this->releaseLockAndCloseHandle($queueFileHandle);

        $this->releaseLockAndCloseHandle($indexFileHandle);
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
     * @return array<string, QueueRecord>
     * @throws Exception
     */
    protected function getQueue(string $queue, mixed $handle = null): array
    {
        if (!file_exists($this->queueFileName($queue))) {
            $this->initQueueFile($queue);

            return [];
        }

        $queueJobs = [];

        foreach ($this->getUnserializedQueueContent($queue, $handle) as $id => $queueJobData) {
            $queueJobs[$id] = QueueRecord::fromArray($queueJobData);
        }

        return $queueJobs;
    }

    /**
     * @param QueueRecord[] $queueData
     */
    protected function saveQueue(array $queueData, mixed $handle): void
    {
        foreach ($queueData as $queueJobId => $queueJob) {
            if ($queueJob instanceof QueueRecord) {
                $queueData[$queueJobId] = $queueJob->toArray();
            }
        }

        fwrite($handle, serialize($queueData));
    }

    /**
     * @throws Exception
     */
    protected function forgetFromQueue(string $queue, string $id, mixed $queueHandle): void
    {
        $queueData = $this->getQueue($queue, $queueHandle);

        if (isset($queueData[$id])) {
            unset($queueData[$id]);
        }

        $this->saveQueue($queueData, $queueHandle);
    }

    /**
     * @throws Exception
     */
    protected function addIdToIndex(QueueRecord $record, mixed $handle): void
    {
        $index = $this->getIndex($handle);

        $index[$record->id] = $record->queue;

        $this->saveIndex($index, $handle);
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    protected function getIndex(mixed $handle = null): array
    {
        if (!file_exists($this->basePath('index'))) {
            touch($this->basePath('index'));

            $handle = $handle ?? $this->openAndLockIndexFile();

            $this->saveIndex([], $handle);

            $this->releaseLockAndCloseHandle($handle);
        }

        return $this->getUnserializedFileContent($this->basePath('index'), $handle);
    }

    /**
     * @return mixed[]
     * @throws Exception
     */
    protected function getUnserializedQueueContent(string $queue, mixed $handle = null): array
    {
        return $this->getUnserializedFileContent($this->queueFileName($queue), $handle);
    }

    /**
     * When this method is called within the process of writing data, it will receive an open file handle as second
     * argument. In this case the data is read using fgets(). Otherwise, it will just use file_get_contents().
     *
     * When there is no open file handle, it could be that some other process has currently locked the file, which
     * could cause file_get_contents() to fail. In that case, just wait a little and try again or throw an exception
     * if it can't read the file
     *
     * @throws Exception
     */
    protected function getUnserializedFileContent(string $filepath, mixed $handle = null): mixed
    {
        if (is_resource($handle)) {
            $content = '';

            while (($buffer = fgets($handle)) !== false) {
                $content .= $buffer;
            }

            rewind($handle);

            $content = @unserialize($content);

            if ($content !== false) {
                return $content;
            }

            throw new Exception('Failed to read or unserialize file');
        }

        $tries = 0;

        while ($tries < 1000) {
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
     * @param resource $handle
     */
    protected function saveIndex(array $index, mixed $handle): void
    {
        fwrite($handle, serialize($index));
    }

    /**
     * @return array<int, resource>
     * @throws Exception
     */
    protected function openAndLockQueueAndIndexFiles(string $queue): array
    {
        $waitTime = null;

        while ($waitTime < 100000) {
            try {
                return [$this->openAndLockQueueFile($queue, false), $this->openAndLockIndexFile(false)];
            } catch (Exception $exception) {
            }

            $waitTime = $waitTime === null ? rand(100, 300) : $waitTime + rand(100, 300);

            usleep($waitTime);
        }

        throw new Exception('Can\'t open or lock file.');
    }

    /**
     * @return resource
     * @throws Exception
     */
    protected function openAndLockQueueFile(string $queue, bool $retry = true): mixed
    {
        if (!file_exists($this->queueFileName($queue))) {
            $this->initQueueFile($queue);
        }

        return $this->openAndLockFile($this->queueFileName($queue), $retry);
    }

    /**
     * @return resource
     * @throws Exception
     */
    protected function openAndLockIndexFile(bool $retry = true): mixed
    {
        return $this->openAndLockFile($this->indexFileName(), $retry);
    }

    protected function openAndLockFile(string $filepath, bool $retry = true): mixed
    {
        $waitTime = null;

        while ($waitTime === null || $waitTime < 50000) {
            $fileHandle = fopen($filepath, 'r+');

            if ($fileHandle) {
                if (flock($fileHandle, LOCK_EX | LOCK_NB)) {
                    return $fileHandle;
                }

                fclose($fileHandle);
            }

            if (!$retry) {
                throw new Exception('Can\'t open or lock file');
            }

            $waitTime = $waitTime === null ? rand(100, 300) : $waitTime + rand(100, 300);

            usleep($waitTime);
        }

        throw new Exception('Can\'t open or lock file.');
    }

    protected function releaseLockAndCloseHandle(mixed $handle): void
    {
        flock($handle, LOCK_UN);

        fclose($handle);
    }

    /**
     * @throws Exception
     */
    protected function initQueueFile(string $queue): void
    {
        touch($this->queueFileName($queue));

        $handle = $this->openAndLockQueueFile($queue);

        $this->saveQueue([], $handle);

        $this->releaseLockAndCloseHandle($handle);
    }

    protected function queueFileName(string $queue): string
    {
        return $this->basePath('queue-' . $queue);
    }

    protected function indexFileName(): string
    {
        return $this->basePath('index');
    }

    protected function basePath(string $fileName): string
    {
        return $this->basePath . (!str_ends_with($this->basePath, '/') ? '/' : '') . $fileName;
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
}
