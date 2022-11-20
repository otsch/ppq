<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Kernel;

it('does not start a second worker process', function () {
    Config::setPath(__DIR__ . '/../_testdata/config/filesystem-ppq.php');

    $firstWorkerProcess = Kernel::ppqCommand('work');

    $firstWorkerProcess->start();

    usleep(50000);

    $secondWorkerProcess = Kernel::ppqCommand('work');

    $secondWorkerProcess->run();

    $firstWorkerProcess->stop(0);

    expect($firstWorkerProcess->getOutput())->not()->toContain('Queues are already working');

    expect($secondWorkerProcess->getOutput())->toContain('Queues are already working');
});
