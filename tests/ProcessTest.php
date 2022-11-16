<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Process;
use PHPUnit\Framework\TestCase;
use Stubs\TestJob;

/** @var TestCase $this */

it('finishes a process that was successfully finished', function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $queueRecord = new QueueRecord('default', TestJob::class);

    $driver = Config::getDriver();

    $driver->add($queueRecord);

    $process = Mockery::mock(\Symfony\Component\Process\Process::class);

    $process->shouldReceive('isRunning')->once()->andReturn(false); // @phpstan-ignore-line

    $process->shouldReceive('isSuccessful')->once()->andReturn(true); // @phpstan-ignore-line

    $process = new Process($queueRecord, $process); // @phpstan-ignore-line

    $process->finish();

    expect($queueRecord->status)->toBe(QueueJobStatus::finished);

    expect($queueRecord->pid)->toBeNull();

    expect($this->getActualOutput())->toContain('Finished job with id');
});

it('finishes a process that is still running', function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $queueRecord = new QueueRecord('default', TestJob::class, QueueJobStatus::running);

    $driver = Config::getDriver();

    $driver->add($queueRecord);

    $process = Mockery::mock(\Symfony\Component\Process\Process::class);

    $process->shouldReceive('isRunning')->once()->andReturn(true); // @phpstan-ignore-line

    $process->shouldReceive('stop')->once(); // @phpstan-ignore-line

    $process->shouldReceive('isSuccessful')->once()->andReturn(false); // @phpstan-ignore-line

    $process->shouldReceive('getErrorOutput')->twice()->andReturn('Execution was cancelled'); // @phpstan-ignore-line

    $process = new Process($queueRecord, $process); // @phpstan-ignore-line

    $process->finish();

    expect($queueRecord->status)->toBe(QueueJobStatus::failed);

    expect($queueRecord->pid)->toBeNull();

    expect($this->getActualOutput())->toContain('Execution was cancelled');
});

it('finishes a process that failed', function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    $queueRecord = new QueueRecord('default', TestJob::class, QueueJobStatus::running);

    $driver = Config::getDriver();

    $driver->add($queueRecord);

    $process = Mockery::mock(\Symfony\Component\Process\Process::class);

    $process->shouldReceive('isRunning')->once()->andReturn(false); // @phpstan-ignore-line

    $process->shouldReceive('isSuccessful')->once()->andReturn(false); // @phpstan-ignore-line

    $process->shouldReceive('getErrorOutput')->once()->andReturn(''); // @phpstan-ignore-line

    $process->shouldReceive('getOutput')->twice()->andReturn('There was an error'); // @phpstan-ignore-line

    $process = new Process($queueRecord, $process); // @phpstan-ignore-line

    $process->finish();

    expect($queueRecord->status)->toBe(QueueJobStatus::failed);

    expect($queueRecord->pid)->toBeNull();

    expect($this->getActualOutput())->toContain('There was an error');
});

it('checks if a running process with a certain pid exists', function () {
    $process = \Symfony\Component\Process\Process::fromShellCommandline('php ' . __DIR__ . '/_testdata/counting.php');

    $process->start();

    $pid = $process->getPid();

    expect($pid)->toBeInt()->toBeGreaterThan(0);

    /** @var int $pid */

    expect(Process::runningPhpProcessWithPidExists($pid))->toBeTrue();

    $process->stop();

    expect(Process::runningPhpProcessWithPidExists($pid))->toBeFalse();
});

it('checks if a running process containing certain strings (in command) exists', function () {
    $process = \Symfony\Component\Process\Process::fromShellCommandline('php ' . __DIR__ . '/_testdata/counting.php');

    $process->start();

    $pid = $process->getPid();

    expect($pid)->toBeInt()->toBeGreaterThan(0);

    /** @var int $pid */

    expect(Process::runningPhpProcessContainingStringsExists(['counting.php']))->toBeTrue();

    $process->stop();

    expect(Process::runningPhpProcessContainingStringsExists(['counting.php']))->toBeFalse();
})->only();

test('checking if a running process containing certain strings exists, exclude the current process', function () {
    $process = \Symfony\Component\Process\Process::fromShellCommandline(
        'php ' . __DIR__ . '/_testdata/check-process-already-running.php'
    );

    $process->run();

    expect($process->getOutput())->toBe('no');
});
