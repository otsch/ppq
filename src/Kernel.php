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

    public static function ppqCommand(
        string $command,
        ?string $logPath = null,
        ?string $iniConfigOption = null,
    ): SymfonyProcess {
        if ($logPath) {
            touch($logPath);
        }

        $iniConfigOption = $iniConfigOption ? '-d ' . $iniConfigOption . ' ' : '';

        $command = 'php ' . $iniConfigOption . self::ppqPath() . ' ' . $command . ' --config=' . Config::getPath();

        if ($logPath) {
            $command .= ' >> ' . $logPath . ' 2>&1';
        }

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
        } elseif ($this->argv->logs()) {
            $this->showLog();
        } elseif ($this->argv->clearQueue()) {
            $this->clearQueue();
        } elseif ($this->argv->clearAllQueues()) {
            $this->clearAllQueues();
        } elseif ($this->argv->flushQueue()) {
            $this->flushQueue();
        } elseif ($this->argv->flushAllQueues()) {
            $this->flushAllQueues();
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

        $errorHandler = $this->getErrorHandler();

        try {
            $job = new $job->jobClass(...$job->args);

            if (!$job instanceof QueueableJob) {
                throw new Exception(
                    'Can\'t run job because it\'s not an implementation of the QueueableJob interface.'
                );
            }

            $job->invoke();
        } catch (Exception $exception) {
            $errorHandler?->handleException($exception);

            $this->fail->withMessage(
                'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL . ' in ' .
                $exception->getFile() . ' on line ' . $exception->getLine()
            );
        }
    }

    /**
     * @throws Exception
     */
    protected function cancelJob(): void
    {
        $job = $this->getJobByIdOrFail();

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
        $queue = $this->argv->subjectQueue();

        if (!$queue || !in_array($queue, Config::getQueueNames(), true)) {
            $this->logger->error('You must define which queue you want to clear. Use clear-all to clear all queues.');

            return;
        }

        Ppq::clear($queue);

        $this->logger->info('Cleared queue ' . $queue);
    }

    protected function flushQueue(): void
    {
        $queue = $this->argv->subjectQueue();

        if (!$queue || !in_array($queue, Config::getQueueNames(), true)) {
            $this->logger->error('You must define which queue you want to flush. Use flush-all to flush all queues.');

            return;
        }

        Ppq::flush($queue);

        $this->logger->info('Flushed queue ' . $queue);
    }

    protected function clearAllQueues(): void
    {
        Ppq::clearAll();

        $this->logger->info('Cleared all queues');
    }

    protected function flushAllQueues(): void
    {
        Ppq::flushAll();

        $this->logger->info('Flushed all queues');
    }

    /**
     * @throws Exception
     */
    protected function showLog(): void
    {
        if ($this->argv->jobId()) {
            $job = $this->getJobByIdOrFail();

            $numberOfLines = $this->argv->lines();

            if (is_numeric($numberOfLines)) {
                $numberOfLines = (int) $numberOfLines;
            } elseif ($numberOfLines === 'all') {
                $numberOfLines = null;
            } else {
                $numberOfLines = 1000;
            }

            Logs::printJobLog($job, $numberOfLines);
        }
    }

    /**
     * @throws Exception
     */
    protected function getErrorHandler(): ?AbstractErrorHandler
    {
        $mainErrorHandler = Config::get('error_handler');

        if (
            isset($mainErrorHandler['class']) &&
            isset($mainErrorHandler['active']) &&
            $mainErrorHandler['active'] === true
        ) {
            $handler = new $mainErrorHandler['class']();

            if (!$handler instanceof AbstractErrorHandler) {
                throw new Exception('Configured error_handler class doesn\'t extend the AbstractErrorHandler class.');
            }

            return $handler;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    protected function getJobByIdOrFail(): QueueRecord
    {
        if (!$this->argv->jobId()) {
            throw new Exception('No or invalid job id.');
        }

        $job = Ppq::find($this->argv->jobId());

        if ($job === null) {
            $this->fail->withMessage('Job with id ' . $this->argv->jobId() . ' not found');

            throw new Exception('This exception should only be possible in the unit tests when mocking $this->fail');
        }

        return $job;
    }
}
