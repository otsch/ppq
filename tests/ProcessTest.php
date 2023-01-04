<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Process;
use Otsch\Ppq\QueueEventListeners;
use PHPUnit\Framework\TestCase;
use Stubs\TestJob;

/** @var TestCase $this */

it('finishes a process that was successfully finished', function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $queueRecord = new QueueRecord('default', TestJob::class);

    $driver = Config::getDriver();

    $driver->add($queueRecord);

    $process = Mockery::mock(\Symfony\Component\Process\Process::class);

    $process->shouldReceive('isSuccessful')->once()->andReturn(true); // @phpstan-ignore-line

    $process = new Process($queueRecord, $process); // @phpstan-ignore-line

    $process->finish(new QueueEventListeners());

    expect($queueRecord->status)->toBe(QueueJobStatus::finished);

    expect($queueRecord->pid)->toBeNull();

    expect($this->getActualOutput())->toContain('Finished job with id');
});

it('finishes a process that failed', function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $queueRecord = new QueueRecord('default', TestJob::class, QueueJobStatus::running);

    $driver = Config::getDriver();

    $driver->add($queueRecord);

    $process = Mockery::mock(\Symfony\Component\Process\Process::class);

    $process->shouldReceive('isSuccessful')->once()->andReturn(false); // @phpstan-ignore-line

    $process->shouldReceive('getErrorOutput')->once()->andReturn(''); // @phpstan-ignore-line

    $process->shouldReceive('getOutput')->twice()->andReturn('There was an error'); // @phpstan-ignore-line

    $process = new Process($queueRecord, $process); // @phpstan-ignore-line

    $process->finish(new QueueEventListeners());

    expect($queueRecord->status)->toBe(QueueJobStatus::failed);

    expect($queueRecord->pid)->toBeNull();

    expect($this->getActualOutput())->toContain('There was an error');
});
