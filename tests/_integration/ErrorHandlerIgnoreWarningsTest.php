<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Utils;
use Stubs\PhpWarningJob;

beforeAll(function () {
    Config::setPath(helper_testConfigPath('error-handler-ignore-warnings.php'));

    WorkerProcess::work();

    helper_cleanUpDataPathQueueFiles();
});

beforeEach(function () {
    // Worker should already be running, but if it somehow died, it will be restarted.
    Config::setPath(helper_testConfigPath('error-handler-ignore-warnings.php'));

    WorkerProcess::work();

    helper_emptyHandlerEventsFile();
});

afterAll(function () {
    WorkerProcess::stop();

    helper_cleanUpDataPathQueueFiles();

    helper_emptyHandlerEventsFile();
});

it('ignores warnings when parameter used in registerHandler()', function () {
    // See Stubs/ErrorHandlerIgnoreWarnings.php
    Config::setPath(helper_testConfigPath('error-handler-ignore-warnings.php'));

    $job = Dispatcher::queue('default')
        ->job(PhpWarningJob::class)
        ->dispatch();

    Utils::tryUntil(function () use ($job) {
        return Ppq::find($job->id)?->status === QueueJobStatus::finished;
    });

    $job = Ppq::find($job->id);

    $handlerEvents = file_get_contents(helper_testDataPath('error-handler-events'));

    expect($job?->status)->toBe(QueueJobStatus::finished)
        ->and($handlerEvents)->not->toContain('PHP Warning: unserialize(): Error at offset 0 of 3 bytes');
});
