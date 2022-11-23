<?php

namespace Integration;

use Exception;
use Otsch\Ppq\Config;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Entities\QueueRecord;
use Otsch\Ppq\Entities\Values\QueueJobStatus;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Logs;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\Utils;
use Stubs\LogLinesTestJob;
use Stubs\LogTestJob;

beforeEach(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');
});

beforeAll(function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    helper_cleanUpDataPathQueueFiles();

    WorkerProcess::work();

    $simpleLogTestJob = new QueueRecord('other_queue', LogTestJob::class, id: '1');

    $linesLogTestJob = new QueueRecord('default', LogLinesTestJob::class, id: '2');

    $driver = new FileDriver();

    $driver->add($simpleLogTestJob);

    $driver->add($linesLogTestJob);

    $jobsFinished = Utils::tryUntil(function () use ($simpleLogTestJob, $linesLogTestJob) {
        return Ppq::find($simpleLogTestJob->id)?->status === QueueJobStatus::finished &&
            Ppq::find($linesLogTestJob->id)?->status === QueueJobStatus::finished;
    }, maxTries: 300, sleep: 25000);

    if (!$jobsFinished) {
        throw new Exception('Log test jobs haven\'t finished yet');
    }

    WorkerProcess::stop();
});

afterAll(function () {
    helper_cleanUpDataPathQueueFiles();
});

it('logs output from queue jobs to a log file in expected path', function () {
    $job = new QueueRecord('other_queue', LogTestJob::class, id: '1');

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
    $job = new QueueRecord('default', LogLinesTestJob::class, id: '2');

    $logCommand = Kernel::ppqCommand('logs ' . $job->id);

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 501');

    expect($logCommandOutputLines[999])->toContain('[INFO] 1500');
});

test('the logs command prints only the last x lines with --lines parameter', function () {
    $job = new QueueRecord('default', LogLinesTestJob::class, id: '2');

    $logCommand = Kernel::ppqCommand('logs ' . $job->id . ' --lines=10');

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 1491');

    expect($logCommandOutputLines[9])->toContain('[INFO] 1500');
});

test('the logs command prints the whole log with --lines=all', function () {
    $job = new QueueRecord('default', LogLinesTestJob::class, id: '2');

    $logCommand = Kernel::ppqCommand('logs ' . $job->id . ' --lines=all');

    $logCommand->run();

    expect($logCommand->isSuccessful())->toBeTrue();

    $logCommandOutput = $logCommand->getOutput();

    $logCommandOutputLines = explode(PHP_EOL, $logCommandOutput);

    expect($logCommandOutputLines[0])->toContain('[INFO] 1');

    expect($logCommandOutputLines[1499])->toContain('[INFO] 1500');
});
