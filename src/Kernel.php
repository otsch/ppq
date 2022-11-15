<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Contracts\QueueableJob;
use Otsch\Ppq\Contracts\Scheduler;
use Symfony\Component\Process\Process as SymfonyProcess;

class Kernel
{
    protected readonly Argv $argv;

    /**
     * @param string[] $argv
     */
    public function __construct(
        array $argv,
        protected Worker $worker = new Worker(),
        protected Signal $signal = new Signal(),
        protected Lister $lister = new Lister(),
        protected Fail $fail = new Fail(),
    ) {
        $this->argv = Argv::make($argv);
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
        } elseif ($this->argv->stopQueues()) {
            $this->signal->setStop();
        } elseif ($this->argv->checkSchedule()) {
            $this->checkSchedule();
        } elseif ($this->argv->list()) {
            $this->lister->list();
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
        if (!$this->argv->jobId()) {
            throw new Exception('No or invalid job id.');
        }

        $job = Config::getDriver()->get($this->argv->jobId());

        if (!$job) {
            $this->fail->withMessage('Job with id ' . $this->argv->jobId() . ' not found');
        } else {
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
}
