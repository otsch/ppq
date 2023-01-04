<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Processes;
use Otsch\Ppq\Utils;
use Stubs\TestJob;
use Stubs\TestJobPhpError;
use Stubs\TestJobThrowingException;

beforeAll(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/event-listeners.php');

    if (!file_exists(__DIR__ . '/../_testdata/datapath/event-listeners-check-file')) {
        touch(__DIR__ . '/../_testdata/datapath/event-listeners-check-file');
    } else {
        file_put_contents(__DIR__ . '/../_testdata/datapath/event-listeners-check-file', '');
    }

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();
});

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/event-listeners.php');

    file_put_contents(__DIR__ . '/../_testdata/datapath/event-listeners-check-file', '');
});

afterAll(function () {
    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::stop();
});

function helper_getListenerCheckFileContent(): string
{
    $fileContent = file_get_contents(__DIR__ . '/../_testdata/datapath/event-listeners-check-file');

    if ($fileContent === false) {
        return '';
    }

    return $fileContent;
}

it('calls event listeners registered for the waiting event, when a new waiting job is added', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    Dispatcher::queue('default')->job(TestJob::class)->args(['countTo' => 10])->dispatch();

    expect(helper_getListenerCheckFileContent())->toBe('default waiting called');
});

it('calls event listeners registered for the running event, when a waiting job is started', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJob::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::running;
    });

    expect(helper_getListenerCheckFileContent())->toBe('default running called');
});

it('calls event listeners registered for the finished event, when a job successfully finished', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJob::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::finished;
    });

    expect(helper_getListenerCheckFileContent())->toBe('default finished called');
});

it('calls event listeners registered for the failed event, when a job failed', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJobThrowingException::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::failed;
    });

    expect(helper_getListenerCheckFileContent())->toBe('default failed called');
});

it('calls event listeners registered for the failed event, when a job has a PHP error', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJobPhpError::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::failed;
    });

    expect(helper_getListenerCheckFileContent())->toBe('default failed called');
});

it('calls event listeners registered for the lost event, when a job is lost', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    // In some environments it somehow takes pretty long to stop the worker process and then the job could already
    // be finished before the process is stopped, so use a pretty big number to count to.
    $queueJob = Dispatcher::queue('default')->job(TestJob::class)->args(['countTo' => 750000000])->dispatch();

    $runningQueueJob = Utils::tryUntil(function () use ($queueJob) {
        $updatedQueueJob = Ppq::find($queueJob->id);

        return $updatedQueueJob?->status === QueueJobStatus::running ? $updatedQueueJob : false;
    });

    expect($runningQueueJob?->status)->toBe(QueueJobStatus::running);

    WorkerProcess::stop();

    Utils::tryUntil(function () use ($runningQueueJob) {
        return !Processes::pidStillExists($runningQueueJob->pid);
    }, maxTries: 5000);

    expect(Processes::pidStillExists($runningQueueJob->pid))->toBeFalse();

    expect(Ppq::find($runningQueueJob->id)?->status)->not()->toBe(QueueJobStatus::finished);

    WorkerProcess::work();

    $updatedQueueJob = Utils::tryUntil(function () use ($runningQueueJob) {
        $updatedQueueJob = Ppq::find($runningQueueJob->id);

        return $updatedQueueJob?->status === QueueJobStatus::lost ? $updatedQueueJob : false;
    });

    expect($updatedQueueJob?->status)->toBe(QueueJobStatus::lost);

    expect(helper_getListenerCheckFileContent())->toBe('default lost called');
});

it('calls event listeners registered for the cancelled event, when a waiting job is cancelled', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJob::class)->dispatch();

    $cancelCommand = Kernel::ppqCommand('cancel ' . $queueJob->id);

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::cancelled;
    });

    expect(Ppq::find($queueJob->id)?->status)->toBe(QueueJobStatus::cancelled);

    expect(helper_getListenerCheckFileContent())->toBe('default cancelled called');
});

it('calls event listeners registered for the cancelled event, when a running job is cancelled', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('default')->job(TestJob::class)->args(['countTo' => 99999999])->dispatch();

    $updatedQueueJob = Utils::tryUntil(function () use ($queueJob) {
        $updatedQueueJob = Ppq::find($queueJob->id);

        return $updatedQueueJob?->status === QueueJobStatus::running ? $updatedQueueJob : false;
    });

    expect($updatedQueueJob->status)->toBe(QueueJobStatus::running);

    $cancelCommand = Kernel::ppqCommand('cancel ' . $queueJob->id);

    $cancelCommand->run();

    expect($cancelCommand->isSuccessful())->toBeTrue();

    $updatedQueueJob = Utils::tryUntil(function () use ($queueJob) {
        $updatedQueueJob = Ppq::find($queueJob->id);

        return $updatedQueueJob?->status === QueueJobStatus::cancelled ? $updatedQueueJob : false;
    });

    // Cancelled state is written immediately, the worker tries to actually cancel a running process on the next
    // worker loop iteration. If actually cancelling the process fails in the worker, the status is set back to running.
    // So, wait a little longer, because the job's status can already be cancelled but the event isn't called yet.
    usleep(400000);

    expect($updatedQueueJob?->status)->toBe(QueueJobStatus::cancelled);

    expect(helper_getListenerCheckFileContent())->toBe('default cancelled called');
});

it('calls only the listeners registered for the queue the job is on', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('other_queue')->job(TestJob::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::finished;
    });

    expect(helper_getListenerCheckFileContent())->toBe('');
});

it('calls all listeners registered for an event', function () {
    expect(helper_getListenerCheckFileContent())->toBe('');

    $queueJob = Dispatcher::queue('other_queue')->job(TestJobThrowingException::class)->dispatch();

    Utils::tryUntil(function () use ($queueJob) {
        return Ppq::find($queueJob->id)?->status === QueueJobStatus::failed;
    });

    expect(helper_getListenerCheckFileContent())
        ->toBe('other queue failed one called other queue failed two called other queue failed three called');
});
