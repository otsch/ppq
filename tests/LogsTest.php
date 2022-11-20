<?php

use Integration\WorkerProcess;
use Otsch\Ppq\Config;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Logs;
use Otsch\Ppq\Ppq;
use PHPUnit\Framework\TestCase;
use Stubs\LogLinesTestJob;
use Stubs\LogTestJob;

beforeAll(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();

    (new FileDriver())->add(new QueueRecord('default', LogLinesTestJob::class, id: '123abc'));

    helper_tryUntil(function () {
        return count(Ppq::waiting('default')) === 0 && count(Ppq::running('default')) === 0;
    });

    WorkerProcess::stop();
});

/** @var TestCase $this */

it('gets a jobs log and gets the last 1000 lines by default', function () {
    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    $logs = Logs::getJobLog($job);

    $logLines = explode(PHP_EOL, $logs);

    expect($logLines)->toHaveCount(1001);

    expect($logLines[0])->toContain('[INFO] 2001');

    expect($logLines[999])->toContain('[INFO] 3000');
});

it('gets the last x lines when you provide a value for param numberOfLines', function () {
    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    $logs = Logs::getJobLog($job, 14);

    $logLines = explode(PHP_EOL, $logs);

    expect($logLines)->toHaveCount(15);

    expect($logLines[0])->toContain('[INFO] 2987');

    expect($logLines[13])->toContain('[INFO] 3000');
});

it('gets the whole log when you set param numberOfLines to null', function () {
    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    $logs = Logs::getJobLog($job, null);

    $logLines = explode(PHP_EOL, $logs);

    expect($logLines)->toHaveCount(3001);

    expect($logLines[0])->toContain('[INFO] 1');

    expect($logLines[2999])->toContain('[INFO] 3000');
});

it('prints a jobs log', function () {
    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    Logs::printJobLog($job);

    $logLines = explode(PHP_EOL, $this->getActualOutput());

    expect($logLines)->toHaveCount(1001);

    expect($logLines[0])->toContain('[INFO] 2001');

    expect($logLines[999])->toContain('[INFO] 3000');
});

it('tells you the log base path', function () {
    expect(realpath(Logs::logPath()))->toBe(__DIR__ . '/_testdata/datapath/logs');
});

it('tells you the log path for a certain queue', function () {
    expect(Logs::queueLogPath('other_queue'))->toBe(__DIR__ . '/_testdata/config/../datapath/logs/other_queue');
});

it('forgets the log file for a queue job', function () {
    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    expect(file_exists(__DIR__ . '/_testdata/datapath/logs/default/' . $job->id . '.log'))->toBeTrue();

    Logs::forget($job);
});

it('creates the log dirs if they don\'t exist yet', function () {
    helper_cleanUpDataPathQueueFiles();

    $logsPath = Logs::logPath();

    $remainingQueueLogDirs = scandir($logsPath);

    if (is_array($remainingQueueLogDirs)) {
        foreach ($remainingQueueLogDirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            rmdir($logsPath . '/' . $dir);
        }
    }

    if (file_exists($logsPath)) {
        rmdir($logsPath);
    }

    $job = new QueueRecord('default', LogTestJob::class, id: '123abc');

    Logs::queueJobLogPath($job);

    expect(file_exists($logsPath))->toBeTrue();

    expect(file_exists($logsPath . '/' . $job->queue))->toBeTrue();
});
