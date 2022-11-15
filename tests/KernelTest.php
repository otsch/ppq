<?php

use Mockery\Mock;
use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Fail;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Lister;
use Otsch\Ppq\Signal;
use Otsch\Ppq\Worker;
use PHPUnit\Framework\TestCase;
use Stubs\InvalidJob;
use Stubs\StaticTestProperties;
use Stubs\TestJob;

/** @var TestCase $this */

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');
});

it('returns an existing ppqPath', function () {
    expect(file_exists(Kernel::ppqPath()))->toBeTrue();
});

it('returns a Symfony Process instance to run a ppq command containing the full ppqPath', function () {
    $process = Kernel::ppqCommand('run-job 123');

    expect($process->getCommandLine())->toContain(Kernel::ppqPath());

    expect($process->getCommandLine())->toEndWith('run-job 123');

    expect($process->getCommandLine())->toStartWith('php');
});

it('calls the bootstrap file defined in the config when run() is called', function () {
    $kernel = new Kernel([]);

    expect(StaticTestProperties::$bootstrapWasCalled)->toBeFalse();

    $kernel->run();

    expect(StaticTestProperties::$bootstrapWasCalled)->toBeTrue();
});

it('calls Worker::workQueues() when argv\'s first argument is work and run() is called', function () {
    $workerMock = Mockery::mock(Worker::class);

    $workerMock->shouldReceive('workQueues')->once(); // @phpstan-ignore-line

    $kernel = new Kernel(['vendor/bin/ppq', 'work'], $workerMock); // @phpstan-ignore-line

    $kernel->run();
});

it('runs a job', function () {
    $queueJob = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($queueJob);

    $kernel = new Kernel(['vendor/bin/ppq', 'run-job', $queueJob->id]);

    $kernel->run();

    expect($this->getActualOutput())->toContain('Successfully finished TestJob');
});

it('throws an Exception when there is no job ID in the argv arguments', function () {
    $queueJob = new QueueRecord('default', TestJob::class);

    Config::getDriver()->add($queueJob);

    $kernel = new Kernel(['vendor/bin/ppq', 'run-job']);

    $kernel->run();
})->throws(Exception::class);

it('fails when the job ID to run is not on the queue', function () {
    $failMock = Mockery::mock(Fail::class);

    $failMock->shouldReceive('withMessage')->once(); // @phpstan-ignore-line

    $queueJob = new QueueRecord('default', TestJob::class);

    $kernel = new Kernel(['vendor/bin/ppq', 'run-job', $queueJob->id], fail: $failMock); // @phpstan-ignore-line

    $kernel->run();
});

it('fails when the job class to run does not implement the QueueableJob interface', function () {
    $failMock = Mockery::mock(Fail::class);

    $failMock->shouldReceive('withMessage')->once(); // @phpstan-ignore-line

    $queueJob = new QueueRecord('default', InvalidJob::class);

    Config::getDriver()->add($queueJob);

    $kernel = new Kernel(['vendor/bin/ppq', 'run-job', $queueJob->id], fail: $failMock); // @phpstan-ignore-line

    $kernel->run();
});

it('calls Signal::setStop() when argv\'s first argument is stop and run() is called', function () {
    $signalMock = Mockery::mock(Signal::class);

    $signalMock->shouldReceive('setStop')->once(); // @phpstan-ignore-line

    $kernel = new Kernel(['vendor/bin/ppq', 'stop'], signal: $signalMock); // @phpstan-ignore-line

    $kernel->run();
});

it('checks the scheduler', function () {
    $kernel = new Kernel(['vendor/bin/ppq', 'check-schedule']);

    $kernel->run();

    expect($this->getActualOutput())->toContain('Schedule checked');
});

it('calls Lister::list() when argv\'s first argument is list and run() is called', function () {
    $listerMock = Mockery::mock(Lister::class);

    $listerMock->shouldReceive('list')->once(); // @phpstan-ignore-line

    $kernel = new Kernel(['vendor/bin/ppq', 'list'], lister: $listerMock); // @phpstan-ignore-line

    $kernel->run();
});
