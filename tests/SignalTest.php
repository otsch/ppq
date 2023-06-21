<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Signal;

beforeEach(function () {
    Config::setPath(helper_testConfigPath('ppq.php'));

    if (file_exists(helper_testDataPath('signal'))) {
        file_put_contents(helper_testDataPath('signal'), '');
    }
});

it('sets the stop signal', function () {
    $signal = new Signal();

    expect($signal->isStop())->toBeFalse();

    $signal->setStop();

    expect($signal->isStop())->toBeTrue();
});

it('resets the stop signal', function () {
    $signal = new Signal();

    $signal->setStop();

    expect($signal->isStop())->toBeTrue();

    $signal->reset();

    expect($signal->isStop())->toBeFalse();
});
