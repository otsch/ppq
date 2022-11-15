<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Lister;
use PHPUnit\Framework\TestCase;
use Stubs\TestJob;

/** @var TestCase $this */

it('lists all the waiting and running jobs in all queues', function () {
    // temporarily switch config, so the driver instance is reset.
    Config::setPath(__DIR__ . '/_testdata/config/min.php');

    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $driver = Config::getDriver();

    $driver->add(new QueueRecord('default', TestJob::class, args: ['one' => 1]));

    $driver->add(new QueueRecord('default', TestJob::class, QueueJobStatus::running, args: ['two' => 2]));

    $driver->add(new QueueRecord('default', TestJob::class, QueueJobStatus::finished, args: ['three' => 3]));

    $driver->add(new QueueRecord('other_queue', TestJob::class, args: ['five' => 5]));

    $driver->add(new QueueRecord('other_queue', TestJob::class, QueueJobStatus::running, args: ['six' => 6]));

    $driver->add(new QueueRecord('other_queue', TestJob::class, QueueJobStatus::failed, args: ['seven' => 7]));

    $driver->add(new QueueRecord('other_queue', TestJob::class, args: ['eight' => 8]));

    $lister = new Lister();

    $lister->list();

    $cliOutput = $this->getActualOutput();

    expect($cliOutput)->toMatch(
        '/Queue: default\n' .
        'id: .+\..+, jobClass: Stubs\\\\TestJob - running\n' .
        'id: .+\..+, jobClass: Stubs\\\\TestJob - waiting\n\n/'
    );

    expect($cliOutput)->toMatch(
        '/Queue: other_queue\n' .
        'id: .+\..+, jobClass: Stubs\\\\TestJob - running\n' .
        'id: .+\..+, jobClass: Stubs\\\\TestJob - waiting\n' .
        'id: .+\..+, jobClass: Stubs\\\\TestJob - waiting\n/'
    );
});
