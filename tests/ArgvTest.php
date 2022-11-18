<?php

use Otsch\Ppq\Argv;

test('workQueues() returns true when the second argv array element is "work"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->workQueues())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'work']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'work']],
    [true, ['vendor/bin/ppq', 'work', 'foo']],
]);

test('stopQueues() returns true when the second argv array element is "stop"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->stopQueues())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'stop']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'stop']],
    [true, ['vendor/bin/ppq', 'stop', 'foo']],
]);

test('clearQueue() returns true when the second argv array element is "clear"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->clearQueue())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'clear']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'clear']],
    [true, ['vendor/bin/ppq', 'clear', 'foo']],
]);

test('clearAllQueues() returns true when the second argv array element is "clear-all"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->clearAllQueues())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'clear-all']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'clear-all']],
    [true, ['vendor/bin/ppq', 'clear-all', 'foo']],
]);

test('it gets the queue to clear from the third argv argument', function (array $argv, string $queueName) {
    expect(Argv::make($argv)->queueToClear())->toBe($queueName);
})->with([
    [['vendor/bin/ppq', 'clear', 'default'], 'default'],
    [['vendor/bin/ppq', 'clear', '*'], '*'],
    [['vendor/bin/ppq', 'clear', 'foo', 'bar'], 'foo'],
]);

test('runJob() returns true when the second argv array element is "run"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->runJob())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'run']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'run']],
    [true, ['vendor/bin/ppq', 'run', 'foo']],
]);

test('cancelJob() returns true when the second argv array element is "cancel"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->cancelJob())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'cancel']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'cancel']],
    [true, ['vendor/bin/ppq', 'cancel', 'foo']],
]);

test(
    'checkSchedule() returns true when the second argv array element is "check-schedule"',
    function (bool $expect, array $argv) {
        expect(Argv::make($argv)->checkSchedule())->toBe($expect);
    }
)->with([
    [true, ['vendor/bin/ppq', 'check-schedule']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'check-schedule']],
    [true, ['vendor/bin/ppq', 'check-schedule', 'foo']],
]);

test('list() returns true when the second argv array element is "list"', function (bool $expect, array $argv) {
    expect(Argv::make($argv)->list())->toBe($expect);
})->with([
    [true, ['vendor/bin/ppq', 'list']],
    [false, ['vendor/bin/ppq', 'foo']],
    [false, ['vendor/bin/ppq', 'foo', 'list']],
    [true, ['vendor/bin/ppq', 'list', 'foo']],
]);

test('jobId() returns the third argv argument', function () {
    expect(Argv::make(['vendor/bin/ppq', 'run', '123.abc'])->jobId())->toBe('123.abc');
});

it('gets a config provided as --c argument at the end', function () {
    expect(
        Argv::make(['vendor/bin/ppq', 'run', '123.abc', '--c=/var/www/project/src/../config/queue.php'])
            ->configPath()
    )->toBe('/var/www/project/src/../config/queue.php');
});
