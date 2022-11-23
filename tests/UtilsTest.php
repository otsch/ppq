<?php

use Otsch\Ppq\Utils;

it('tries until the callback returns true if max tries aren\'t reached', function () {
    $before = (int) (microtime(true) * 1000000);

    Utils::tryUntil(function () use ($before) {
        return ($before + 150000) < (int) (microtime(true) * 1000000);
    });

    $after = (int) (microtime(true) * 1000000);

    expect($after - $before)->toBeGreaterThan(150000);

    expect($after - $before)->toBeLessThan(200000);
});

it('tries until the max tries limit is reached when it does not return true until then', function () {
    $before = (int) (microtime(true) * 1000000);

    Utils::tryUntil(function () use ($before) {
        return ($before + 150000) < (int) (microtime(true) * 1000000);
    }, maxTries: 10);

    $after = (int) (microtime(true) * 1000000);

    expect($after - $before)->toBeGreaterThan(100000);

    expect($after - $before)->toBeLessThan(150000);
});
