<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\ListCommand;
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

    $listCommand = new ListCommand();

    $listCommand->list();

    $cliOutput = $this->getActualOutput();

    expect($cliOutput)->toMatch(
        '/' .
        '\s+DEFAULT\n_+\n.*\n-*\n' .
        '\|\s*[a-z0-9\.]{0,50}\s*\|\s*running\s*\|\s*Stubs\\\\TestJob\s*\|\s*\[\'two\' => 2\]\s*\|\n' .
        '\|\s*[a-z0-9\.]{0,50}\s*\|\s*waiting\s*\|\s*Stubs\\\\TestJob\s*\|\s*\[\'one\' => 1\]\s*\|\n' .
        '/'
    );

    expect($cliOutput)->toMatch(
        '/' .
        '\s+OTHER_QUEUE\n_+\n.*\n-*\n' .
        '\|\s*[a-z0-9\.]{0,50}\s*\|\s*running\s*\|\s*Stubs\\\\TestJob\s*\|\s*\[\'six\' => 6\]\s*\|\n' .
        '\|\s*[a-z0-9\.]{0,50}\s*\|\s*waiting\s*\|\s*Stubs\\\\TestJob\s*\|\s*\[\'five\' => 5\]\s*\|\n' .
        '\|\s*[a-z0-9\.]{0,50}\s*\|\s*waiting\s*\|\s*Stubs\\\\TestJob\s*\|\s*\[\'eight\' => 8\]\s*\|\n' .
        '/'
    );
});
