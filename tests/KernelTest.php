<?php

namespace tests;

use Otsch\Ppq\Kernel;

it('is instantiable', function () {
    $kernel = new Kernel([]);

    expect($kernel)->toBeInstanceOf(Kernel::class);
});
