<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Logs;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Utils;
use Stubs\ExceptionJob;
use Stubs\PhpErrorJob;
use Stubs\PhpParseErrorJob;
use Stubs\PhpWarningJob;

beforeAll(function () {
    Config::setPath(helper_testConfigPath('error-handlers.php'));

    WorkerProcess::work();

    helper_cleanUpDataPathQueueFiles();
});

beforeEach(function () {
    // Worker should already be running, but if it somehow died, it will be restarted.
    Config::setPath(helper_testConfigPath('error-handlers.php'));

    WorkerProcess::work();

    helper_emptyHandlerEventsFile();
});

afterAll(function () {
    WorkerProcess::stop();

    helper_cleanUpDataPathQueueFiles();

    helper_emptyHandlerEventsFile();
});

it('handles an uncaught exception', function () {
    $job = Dispatcher::queue('default')
        ->job(ExceptionJob::class)
        ->dispatch();

    Utils::tryUntil(function () use ($job) {
        return Ppq::find($job->id)?->status === QueueJobStatus::failed;
    });

    $job = Ppq::find($job->id);

    $handlerEvents = file_get_contents(helper_testDataPath('error-handler-events'));

    expect($job?->status)->toBe(QueueJobStatus::failed)
        ->and($handlerEvents)->toContain('Exception: This is an uncaught test exception');
});

it('handles a PHP warning', function () {
    $job = Dispatcher::queue('default')
        ->job(PhpWarningJob::class)
        ->dispatch();

    Utils::tryUntil(function () use ($job) {
        return Ppq::find($job->id)?->status === QueueJobStatus::finished;
    });

    $job = Ppq::find($job->id);

    $handlerEvents = file_get_contents(helper_testDataPath('error-handler-events'));

    expect($job?->status)->toBe(QueueJobStatus::finished)
        ->and($handlerEvents)->toContain('PHP Warning: file_get_contents(')
        ->and($handlerEvents)->toContain('Failed to open stream: No such file or directory');
});

it('handles a PHP error', function () {
    $job = Dispatcher::queue('default')
        ->job(PhpErrorJob::class)
        ->dispatch();

    Utils::tryUntil(function () use ($job) {
        return Ppq::find($job->id)?->status === QueueJobStatus::failed;
    });

    $job = Ppq::find($job->id);

    $handlerEvents = file_get_contents(helper_testDataPath('error-handler-events'));

    expect($job?->status)->toBe(QueueJobStatus::failed)
        ->and($handlerEvents)->toContain('PHP Error: Uncaught Error: Call to undefined method');
});

it('handles a PHP parse error', function () {
    $job = Dispatcher::queue('default')
        ->job(PhpParseErrorJob::class) // @phpstan-ignore-line
        ->dispatch();

    Utils::tryUntil(function () use ($job) {
        return Ppq::find($job->id)?->status === QueueJobStatus::failed;
    });

    $job = Ppq::find($job->id);

    $handlerEvents = file_get_contents(helper_testDataPath('error-handler-events'));

    expect($job?->status)->toBe(QueueJobStatus::failed)
        ->and($handlerEvents)->toContain('PHP Parse Error: syntax error, unexpected identifier "error"');
});
