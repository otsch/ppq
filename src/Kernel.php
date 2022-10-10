<?php

namespace Otsch\Ppq;

use Exception;
use Otsch\Ppq\Contracts\QueueableJob;
use Otsch\Ppq\Contracts\Scheduler;

class Kernel
{
    protected readonly Argv $argv;

    /**
     * @param string[] $argv
     */
    public function __construct(array $argv)
    {
        $this->argv = Argv::make($argv);
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $this->bootstrap();

        if ($this->argv->workQueues()) {
            (new Manager())->workQueues();
        } elseif ($this->argv->runJob()) {
            $this->runJob();
        } elseif ($this->argv->stopQueues()) {
            (new Signal())->setStop();
        } elseif ($this->argv->checkSchedule()) {
            $this->checkSchedule();
        } elseif ($this->argv->list()) {
            (new Manager())->list();
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
            error_log('Job with id ' . $this->argv->jobId() . ' not found');

            exit(1);
        }

        try {
            $job = new $job->jobClass(...$job->args);

            if (!$job instanceof QueueableJob) {
                throw new Exception(
                    'Can\'t run job because it\'s not an implementation of the QueueableJob interface.'
                );
            }

            $job->invoke();
        } catch (Exception $exception) {
            error_log($exception->getMessage());

            exit(1);
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
