<?php

namespace Integration;

use Otsch\Ppq\Config;
use Otsch\Ppq\Dispatcher;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Logs;
use Otsch\Ppq\Ppq;
use Stubs\LogLinesTestJob;
use Stubs\LogTestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    WorkerProcess::work();
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();
});

afterAll(function () {
    WorkerProcess::stop();

    helper_cleanUpDataPathQueueFiles();
});

it('logs output from queue jobs to a log file in expected path', function () {
    $job = Dispatcher::queue('other_queue')
        ->job(LogTestJob::class)
        ->dispatch();

    helper_tryUntil(function () {
        return count(Ppq::waiting('other_queue')) === 0 && count(Ppq::running('other_queue')) === 0;
    }, sleep: 50000);

    $logFilePath = Logs::queueJobLogPath($job);

    expect(file_exists($logFilePath))->toBeTrue();

    $logFileContent = file_get_contents($logFilePath);

    expect($logFileContent)->toContain('[INFO] some info');

    expect($logFileContent)->toContain('[WARNING] ohoh, this is a warning');

    expect($logFileContent)->toContain('[NOTICE] Just want you to know that...');

    $logCommand = Kernel::ppqCommand('logs ' . $job->id);

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    expect($logCommandOutput)->toContain('[INFO] some info');

    expect($logCommandOutput)->toContain('[WARNING] ohoh, this is a warning');

    expect($logCommandOutput)->toContain('[NOTICE] Just want you to know that...');
});

test('the logs command prints the last 1000 lines by default', function () {
    $job = Dispatcher::queue('default')
        ->job(LogLinesTestJob::class)
        ->dispatch();

    helper_tryUntil(function () {
        return count(Ppq::waiting('default')) === 0 && count(Ppq::running('default')) === 0;
    }, sleep: 50000);

    $logCommand = Kernel::ppqCommand('logs ' . $job->id);

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 2001');

    expect($logCommandOutputLines[999])->toContain('[INFO] 3000');
});

test('the logs command prints only the last x lines with --lines parameter', function () {
    $job = Dispatcher::queue('other_queue')
        ->job(LogLinesTestJob::class)
        ->dispatch();

    helper_tryUntil(function () {
        return count(Ppq::waiting('other_queue')) === 0 && count(Ppq::running('other_queue')) === 0;
    }, sleep: 50000);

    $logCommand = Kernel::ppqCommand('logs ' . $job->id . ' --lines=10');

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 2991');

    expect($logCommandOutputLines[9])->toContain('[INFO] 3000');
});

test('the logs command prints the whole log with --lines=all', function () {
    $job = Dispatcher::queue('other_queue')
        ->job(LogLinesTestJob::class)
        ->dispatch();

    helper_tryUntil(function () {
        return count(Ppq::waiting('other_queue')) === 0 && count(Ppq::running('other_queue')) === 0;
    }, sleep: 50000);

    $logCommand = Kernel::ppqCommand('logs ' . $job->id . ' --lines=all');

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 1');

    expect($logCommandOutputLines[2999])->toContain('[INFO] 3000');
});
