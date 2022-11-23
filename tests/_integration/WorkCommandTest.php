<?php

use Integration\WorkerProcess;
use Otsch\Ppq\Config;
use Otsch\Ppq\Kernel;
use Otsch\Ppq\Utils;

it('does not start a second worker process', function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    WorkerProcess::work();

    $isWorking = Utils::tryUntil(function () {
        return \Otsch\Ppq\WorkerProcess::isWorking();
    });

    expect($isWorking)->toBeTrue();

    $secondWorkerProcess = Kernel::ppqCommand('work');

    $secondWorkerProcess->run();

    WorkerProcess::stop();

    Utils::tryUntil(function () {
        return \Otsch\Ppq\WorkerProcess::isWorking() === false;
    });

    expect(\Otsch\Ppq\WorkerProcess::isWorking())->toBeFalse();

    expect($secondWorkerProcess->getOutput())->toContain('Queues are already working');
});
