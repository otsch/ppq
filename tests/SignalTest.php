<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Signal;

beforeEach(function () {
    Config::setPath(__DIR__ . '/_testdata/config/ppq.php');

    if (file_exists(__DIR__ . '/_testdata/datapath/signal')) {
        file_put_contents(__DIR__ . '/_testdata/datapath/signal', '');
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
