# PPQ
## (Pew Pew Queue or also PHP Queue)

This is a very simple PHP queue system to run background tasks.

ℹ️ It's currently in a very early stage, so don't use it in production yet! Until v1.0 there can be changes that are breaking backwards compatibility in any minor version.

Features:
* Runs on __filesystem only__! You don't need any redis, database or any other infrastructure dependency (except ideally something like supervisor to assure the queue is always running, but that's not a must).
* All background tasks are started in separate (child) PHP processes. Therefor __code changes__ in job classes __immediately affect newly started jobs__ without restarting anything.
* __Gracefully stop the queue worker__ => don't start new jobs and wait until currently running jobs are finished.
* Define __as many queues as you want__ and the limits __how many jobs__ they should run __in parallel__ (at most).

## Installation

```php
composer require otsch/ppq
```

## Usage

### Config

The system needs a config file, defining at least a `datapath` (only mandatory setting). Below you can see a config file with all available config options.

```php
<?php

use Otsch\Ppq\Drivers\FileDriver;

return [
    /**
     * The system in any case needs a datapath, which is a path to a directory on your system,
     * that it can use to save some things about the state of the queues.
     */
    'datapath' => __DIR__ . '/../data/queue',

    /**
     * Currently, the only driver shipped with the package is the `FileDriver`.
     * So you only need to define a driver here, if you build another driver yourself.
     *
     * Using the FileDriver, the system works with filesystem only, no redis or database
     * or any other infrastructure dependency.
     */
    'driver' => FileDriver::class,

    /**
     * If you need to bootstrap your app before a job is executed (because e.g. you need some
     * framework dependencies in your job code), this should be the file path where
     * bootstrapping is done.
     */
    'bootstrap_file' => null,

    /** 
     * Here you can define your queues.
     */
    'queues' => [

        /** 
         * For example, you can use a default queue for most of your background tasks that
         * occur from time to time.
         */
        'default' => [
            /**
             * You should define how many jobs the queue should run in parallel at max.
             */
            'concurrent_jobs' => 2,
            
            /**
             * Optionally define how many past jobs the queue should remember until it forgets (removes)
             * older past (finished, failed, lost, cancelled) jobs. Default value is 100.
             */
            'keep_last_x_past_jobs' => 200,
            
            /**
             * Optionally define event listeners for certain events on this queue (more about this further below).
             */
            'listeners' => [
                'waiting' => WaitingEvent::class,
                'running' => RunningEvent::class,
                'finished' => [FinishedEventOne::class, FinishedEventTwo::class],
            ]
        ],

        /**
         * You can make a separate queue for example, if you're having a certain kind of job
         * that will be run very often and would flood the default queue and maybe cause
         * other more important jobs to wait.
         */
        'special_queue' => [
            'concurrent_jobs' => 4,
        ],

    ],

    /**
     * You can define a Scheduler that will be called when you run the
     * `php vendor/bin/ppq check-schedule` command.
     * More about Schedulers further below.
     */
    'scheduler' => [
        'class' => Scheduler::class,
        
        /**
         * The 'active' setting can be used to run scheduled jobs only in certain environments.
         */
        'active' => false,
    ],
];
```

The config file should be in `/config/ppq.php` from the root of your project.

### Command Line

You control the queue via command line. Here are the available commands:

#### Run/Work all the queues

```bash
php vendor/bin/ppq work
```

The `work` command is used to run the queues. As long as you want the queue to listen for new jobs and run them, this command needs to run. So, ideally you'll start this with a system like supervisor.

If you change your config or update the ppq package you need to restart it, so it'll run with the changed code. If there are changes only in the code of your own queue jobs, it's not necessary to restart, because the system spawns separate PHP processes when starting queued jobs. This means every job started after you change something, automatically runs with the changed codebase.

The `work` command outputs messages when new jobs are started or finished (/failed). When you manually start the queue on the command line you'll see it there, when you start the queue with supervisor, it's recommended to define a file where stdout is written to, so you can have a look at it in retrospect.

#### Stop Working the Queues Gracefully

```bash
php vendor/bin/ppq stop
```

The `stop` command will give a signal to the running queue worker process and cause that it doesn't start any further queued jobs but finally exit when all currently running jobs are finished. This way you don't interrupt any running jobs when you need to restart the queue worker. If you run the queue worker process with supervisor configured to autorestart, you don't need to worry about manually restarting the queue worker when all the currently running jobs are finished.

#### List all Queues and their Running/Waiting Jobs

```bash
php vendor/bin/ppq list
```

Lists all the queues that you defined in your config and their currently running and waiting jobs.

#### Get Logs of a Job

```bash
php vendor/bin/ppq logs 1a2b3c.456def
```

By default, the `logs` command prints at max the last 1000 logged lines. If you want to get more or less, you can use the `--lines` option:

```bash
php vendor/bin/ppq logs 1a2b3c.456def --lines=1500
```

Or if you just want to get all logs for the job:

```bash
php vendor/bin/ppq logs 1a2b3c.456def --lines=all
```

#### Cancel a Waiting or Running Job by its ID

```bash
php vendor/bin/ppq cancel 1a2b3c.456def
```

#### Clearing Queues

If you accidentally dispatched a lot of jobs to a queue and just want to remove them, you can simply clear the queue:

```bash
php vendor/bin/ppq clear queue_name
```

You can also clear all configured queues:

```bash
php vendor/bin/ppq clear-all
```

#### Calling the Scheduler to start due Jobs

```bash
php vendor/bin/ppq check-schedule
```

This will call the `checkScheduleAndQueue()` method of the class you've defined in your config as `['scheduler']['class']`. More about this further below.

### Job Classes

The Job classes that you can dispatch to your queues must implement the `Otsch\Ppq\Contracts\QueueableJob` interface.

```php
use Otsch\Ppq\Contracts\QueueableJob;
use Otsch\Ppq\Loggers\EchoLogger;

class TestJob implements QueueableJob
{
    public function __construct(int $arg = 1)
    {
    }

    public function invoke(): void
    {
        (new EchoLogger())->info('hello');

        usleep(rand(2000, 500000));
    }
}

```

### Dispatching Jobs

For dispatching jobs to your queues there is the `Dispatcher` class. A simple example:

```php
use Your\App\TestJob;

Dispatcher::queue('default')
    ->job(TestJob::class)
    ->dispatch();
```

This will dispatch the `Your\App\TestJob` to the `default` queue. In case you want to keep track of a queue job: the `dispatch()` method returns an instance of `QueueRecord` with the `id` of the job on the queue.

#### Arguments

If your job has some parameters, you can pass scalar value arguments to the job class instance using the `arguments()` method. Let's say your job class looks like this:

```php
class MyJob implements QueueableJob
{
    public function __construct(
        private readonly string $foo,
        private readonly string $bar,
    ) {
    }

    public function invoke(): void
    {
        // Do something with $this->foo and $this->bar
    }
}
```

Then you can provide `foo` and `bar` like:

```php
use Your\App\MyJob;

Dispatcher::queue('default')
    ->job(MyJob::class)
    ->args(['foo' => 'boo', 'bar' => 'far'])
    ->dispatch();
```

As mentioned this works only with scalar values.

#### Dispatch a Job only if it isn't on the Queue yet

```php
use Your\App\MyJob;

Dispatcher::queue('default')
    ->job(MyJob::class)
    ->args()
    ->dispatchIfNotYetInQueue();
```

This will start the job only if it's not already waiting or running on the default queue. If your job has arguments, it will only not dispatch the job when another job is currently waiting or running with the exact same arguments. If you want to make it depend on only one of the arguments, you can do this:

```php
use Your\App\MyJob;

Dispatcher::queue('default')
    ->job(MyJob::class)
    ->args(['foo' => 'boo', 'bar' => 'far'])
    ->dispatchIfNotYetInQueue(['foo']);
```

This will not dispatch the job if another job is currently waiting or running with arg `foo` being `boo`. It won't care about the `bar` argument being different.

### Queue Events

Via the config you can register listeners for queue events. The available events are:

#### waiting
Listeners for the `waiting` event are called whenever a new job is dispatched to the queue it's listening to.

#### running
Listeners for the `running` event are called when a queued job is started.

#### finished
Listeners for the `finished` event are called when a job successfully finished.

#### failed
Listeners for the `failed` event are called when a job failed for some reason.

#### lost
Listeners for the `lost` event are called when a queue somehow lost track of a job process. This can happen when the worker process was killed or had an error and the job process died, finished or failed before the worker was restarted.

#### cancelled
Listeners for the `failed` event are called when a job was manually cancelled.

To add an event listener you need to make a class, implementing the `QueueEventListener` interface:

```php
use Otsch\Ppq\Contracts\QueueEventListener;
use Otsch\Ppq\Entities\QueueRecord;

class RunningEventListener implements QueueEventListener
{
    public function invoke(QueueRecord $queueRecord): void
    {
        // Whatever you want to do when this event occurs.
    }
}
```

And add it to the config, to the queue it should listen to:

```php
use Otsch\Ppq\Drivers\FileDriver;

return [
    'datapath' => __DIR__ . '/../data/queue',
    'queues' => [
        'default' => [
            'concurrent_jobs' => 3,

            'listeners' => [
                'running' => RunningEventListener::class,
            ]
        ],
        'other_queue' => [
            'concurrent_jobs' => 2,
        ],
    ],
];
```

### Scheduling

The scheduler class you've defined in the config file, must implement the `Otsch\Ppq\Contracts\Scheduler` interface. The system currently doesn't do a lot for you regarding scheduling. You can completely implement it yourself, the only thing the system does is call the `checkScheduleAndQueue()` method of your scheduler class when the `php vendor/bin/ppq check-schedule` command is run. So here's a very simple example if you want to schedule some job to run every hour at 15 minutes after the full hour:

```php
use Your\App\TestJob;
use Otsch\Ppq\Contracts\Scheduler as SchedulerInterface;
use Otsch\Ppq\Dispatcher;

class Scheduler implements SchedulerInterface
{
    public function __construct()
    {
    }

    public function checkScheduleAndQueue(): void
    {
        if (date('i') === '15') {
            Dispatcher::queue('default')
                ->job(TestJob::class)
                ->dispatch();
        }
    }
}
```

So, if the `php vendor/bin/ppq check-schedule` command is run exactly at 15 minutes after the full hour, it will dispatch the `Your\App\TestJob` to the `default` queue. This means you need to regularly run the `check-schedule` command. For this you can add a crontab to your system like: 

```
* * * * * php /path/to/your/project/vendor/bin/ppq check-schedule
```
