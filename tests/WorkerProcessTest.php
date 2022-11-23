<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Ppq;
use Otsch\Ppq\WorkerProcess;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/filesystem-ppq.php');

    if (file_exists(Ppq::dataPath('workerprocess'))) {
        unlink(Ppq::dataPath('workerprocess'));
    }
});

afterAll(function () {
    if (file_exists(Ppq::dataPath('workerprocess'))) {
        unlink(Ppq::dataPath('workerprocess'));
    }
});

it('writes pid and time to the workerprocess file in the datapath when set() is called', function () {
    expect(WorkerProcess::get())->toBeEmpty();

    WorkerProcess::set();

    expect(file_exists(Ppq::dataPath('workerprocess')))->toBeTrue();

    $processData = WorkerProcess::get();

    expect($processData)->toBeArray();

    /** @var array<string, int> $processData */

    expect($processData)->toHaveKey('pid')->toHaveKey('time');

    expect($processData['pid'])->toBe(getmypid());

    expect((microtime(true) * 1000000) - $processData['time'])->toBeLessThan(100000);
});

test(
    'isWorking() is true after set() is called, if set() or heartbeat() is not called after that, it automatically ' .
    'returns true, one second later',
    function () {
        expect(WorkerProcess::isWorking())->toBeFalse();

        WorkerProcess::set();

        expect(WorkerProcess::isWorking())->toBeTrue();

        $time = WorkerProcess::getTime();

        while (($time + 950000) > (int) (microtime(true) * 1000000)) {
            expect(WorkerProcess::isWorking())->toBeTrue();

            usleep(50000);
        }

        usleep(50000);

        expect($time + 1000000)->toBeLessThan((int) (microtime(true) * 1000000));

        expect(WorkerProcess::isWorking())->toBeFalse();
    }
);

test('heartbeat() updates the time', function () {
    WorkerProcess::set();

    $time = WorkerProcess::getTime();

    WorkerProcess::heartbeat();

    expect(WorkerProcess::getTime())->toBeGreaterThan($time);
});

it('persists the pid', function () {
    expect(WorkerProcess::getPid())->toBeNull();

    WorkerProcess::set();

    expect(WorkerProcess::getPid())->toBeInt();
});

it('resets the persisted pid and time when unset() is called', function () {
    WorkerProcess::set();

    expect(WorkerProcess::getPid())->toBeInt();

    expect(WorkerProcess::getTime())->toBeInt();

    WorkerProcess::unset();

    expect(WorkerProcess::getPid())->toBeNull();

    expect(WorkerProcess::getTime())->toBeNull();
});
