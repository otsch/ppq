<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Contracts\QueueableJob;
use Otsch\Ppq\Contracts\Scheduler;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Loggers\EchoLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class Kernel
{
    protected readonly Argv $argv;

    protected readonly LoggerInterface $logger;

    /**
     * @param string[] $argv
     */
    public function __construct(
        array $argv,
        protected Worker $worker = new Worker(),
        protected Signal $signal = new Signal(),
        protected ListCommand $listCommand = new ListCommand(),
        protected Fail $fail = new Fail(),
    ) {
        $this->argv = Argv::make($argv);

        $this->logger = new EchoLogger();
    }

    public static function ppqCommand(string $command): SymfonyProcess
    {
        $command = 'php ' . self::ppqPath() . ' ' . $command . ' --c=' . Config::getPath();

        return SymfonyProcess::fromShellCommandline($command);
    }

    public static function ppqPath(): string
    {
        return __DIR__ . '/../bin/ppq';
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->setConfigPath();

        $this->bootstrap();

        if ($this->argv->workQueues()) {
            $this->worker->workQueues();
        } elseif ($this->argv->runJob()) {
            $this->runJob();
        } elseif ($this->argv->cancelJob()) {
            $this->cancelJob();
        } elseif ($this->argv->stopQueues()) {
            $this->signal->setStop();
        } elseif ($this->argv->checkSchedule()) {
            $this->checkSchedule();
        } elseif ($this->argv->list()) {
            $this->listCommand->list();
        } elseif ($this->argv->clearQueue()) {
            $this->clearQueue();
        } elseif ($this->argv->clearAllQueues()) {
            $this->clearAllQueues();
        }
    }

    protected function setConfigPath(): void
    {
        $providedConfigPath = $this->argv->configPath();

        if ($providedConfigPath) {
            Config::setPath($providedConfigPath);
        }
    }

    protected function bootstrap(): void
    {
        $bootstrapFile = Config::get('bootstrap_file');

        if ($bootstrapFile !== null) {
            require_once($bootstrapFile);
        }
    }

    /**
     * @throws Exception
     */
    protected function runJob(): void
    {
        $job = $this->getJobByIdOrFail();

        /** @var QueueRecord $job */

        try {
            $job = new $job->jobClass(...$job->args);

            if (!$job instanceof QueueableJob) {
                throw new Exception(
                    'Can\'t run job because it\'s not an implementation of the QueueableJob interface.'
                );
            }

            $job->invoke();
        } catch (Exception $exception) {
            $this->fail->withMessage($exception->getMessage());
        }
    }

    protected function cancelJob(): void
    {
        $job = $this->getJobByIdOrFail();

        /** @var QueueRecord $job */

        Ppq::cancel($job->id);
    }

    /**
     * @throws Exception
     */
    protected function checkSchedule(): void
    {
        $schedulerConfig = Config::get('scheduler');

        if (
            isset($schedulerConfig['class']) &&
            isset($schedulerConfig['active']) &&
            $schedulerConfig['active'] === true
        ) {
            $scheduler = new $schedulerConfig['class']();

            if (!$scheduler instanceof Scheduler) {
                throw new Exception('Configured scheduler class doesn\'t implement the Scheduler interface.');
            }

            $scheduler->checkScheduleAndQueue();
        }
    }

    protected function clearQueue(): void
    {
        $queueToClear = $this->argv->queueToClear();

        if (!$queueToClear) {
            $this->logger->error('You must define which queue you want to clear. Use * to clear all queues.');

            return;
        }

        if (!in_array($queueToClear, Config::getQueueNames(), true)) {
            $this->logger->error('You must define which queue you want to clear. Use clear-all to clear all queues.');

            return;
        }

        Ppq::clear($queueToClear);

        $this->logger->info('Cleared queue ' . $queueToClear);
    }

    protected function clearAllQueues(): void
    {
        Ppq::clearAll();

        $this->logger->info('Cleared all queues');
    }

    protected function getJobByIdOrFail(): ?QueueRecord
    {
        if (!$this->argv->jobId()) {
            throw new Exception('No or invalid job id.');
        }

        $job = Ppq::find($this->argv->jobId());

        if ($job === null) {
            $this->fail->withMessage('Job with id ' . $this->argv->jobId() . ' not found');
        }

        return $job;
    }
}
