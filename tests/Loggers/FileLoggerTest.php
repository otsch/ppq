<?php

use Otsch\Ppq\Loggers\FileLogger;
use PHPUnit\Framework\TestCase;

function helper_testLogFilePath(): string
{
    return __DIR__ . '/../_testdata/datapath/logfile';
}

function helper_getLogFileContent(): string
{
    $content = file_get_contents(helper_testLogFilePath());

    return $content !== false ? $content : '';
}

beforeEach(function () {
    if (file_exists(helper_testLogFilePath())) {
        file_put_contents(helper_testLogFilePath(), '');
    }
});

/** @var TestCase $this */

test('It writes a message to a log file', function () {
    $logger = new FileLogger(helper_testLogFilePath());

    $logger->log('info', 'Some log message.');

    expect(helper_getLogFileContent())->toContain('Some log message.');
});

test('It adds the log level', function () {
    $logger = new FileLogger(helper_testLogFilePath());

    $logger->log('alert', 'Everybody panic!');

    expect(helper_getLogFileContent())->toContain('[ALERT]');
});

test('It starts with printing the time', function () {
    $logger = new FileLogger(helper_testLogFilePath());

    $logger->log('warning', 'Warn about something.');

    expect(helper_getLogFileContent())->toMatch('/^\d\d:\d\d:\d\d:\d\d\d\d\d\d/');
});

test('It has methods for all the log levels', function ($logLevel) {
    $logger = new FileLogger(helper_testLogFilePath());

    $logger->{$logLevel}('Some message');

    $logFileContent = helper_getLogFileContent();

    expect($logFileContent)->toContain('Some message');

    expect($logFileContent)->toContain('[' . strtoupper($logLevel) . ']');
})->with([
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
]);

it('appends consecutive messages to the file and does not overwrite older messages', function () {
    $logger = new FileLogger(helper_testLogFilePath());

    $logger->info('hello world');

    expect(helper_getLogFileContent())->toContain('[INFO] hello world');

    $logger->error('sh**');

    $logFileContent = helper_getLogFileContent();

    expect($logFileContent)->toContain('[INFO] hello world');

    expect($logFileContent)->toContain('[ERROR] sh**');

    expect(strpos($logFileContent, '[INFO] hello world'))->toBeLessThan(strpos($logFileContent, '[ERROR] sh**'));
});
