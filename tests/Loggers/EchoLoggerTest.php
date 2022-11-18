<?php

namespace Loggers;

use Otsch\Ppq\Loggers\EchoLogger;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

test('It prints a message', function () {
    $logger = new EchoLogger();

    $logger->log('info', 'Some log message.');

    $output = $this->getActualOutput();

    expect($output)->toContain('Some log message.');
});

test('It prints the log level', function () {
    $logger = new EchoLogger();

    $logger->log('alert', 'Everybody panic!');

    $output = $this->getActualOutput();

    expect($output)->toContain('[ALERT]');
});

test('It starts with printing the time', function () {
    $logger = new EchoLogger();

    $logger->log('warning', 'Warn about something.');

    expect($this->getActualOutput())->toMatch('/^\d\d:\d\d:\d\d:\d\d\d\d\d\d/');
});

test('It has methods for all the log levels', function ($logLevel) {
    $logger = new EchoLogger();

    $logger->{$logLevel}('Some message');

    $output = $this->getActualOutput();

    expect($output)->toContain('Some message');

    expect($output)->toContain('[' . strtoupper($logLevel) . ']');
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
