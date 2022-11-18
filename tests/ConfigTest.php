<?php

use Otsch\Ppq\Config;
use Otsch\Ppq\Drivers\FileDriver;
use Otsch\Ppq\Exceptions\InvalidQueueDriverException;
use Stubs\Scheduler;
use Stubs\SimpleInMemoryDriver;

function helper_configFilePath(string $configFile = 'min.php'): string
{
    return __DIR__ . '/_testdata/config/' . $configFile;
}

test(
    'by default it looks for the config file in /../../../../config/ppq.php from the src dir of this package',
    function () {
        Config::get('something');
    }
)->expectErrorMessageMatches('/require.+src\/\.\.\/\.\.\/\.\.\/\.\.\/config\/ppq\.php\): Failed to open/');

it('looks for the config file in the path you set via the setPath() method', function () {
    Config::setPath(helper_configFilePath());

    expect(Config::get('datapath'))->toBeString();
});

test('you can get the config path using the getPath() method', function () {
    Config::setPath('/var/www/project/config/yolo.php');

    expect(Config::getPath())->toBe('/var/www/project/config/yolo.php');
});

test('the driver method returns a FileDriver if no other driver ist configured', function () {
    Config::setPath(helper_configFilePath());

    expect(Config::getDriver())->toBeInstanceOf(FileDriver::class);
});

it('returns an instance of the driver you configure', function () {
    Config::setPath(helper_configFilePath('custom-driver.php'));

    expect(Config::getDriver())->toBeInstanceOf(SimpleInMemoryDriver::class);
});

it('throws an Exception when the configured driver does not implement the QueueDriver interface', function () {
    Config::setPath(helper_configFilePath('invalid-custom-driver.php'));

    Config::getDriver();
})->throws(InvalidQueueDriverException::class);

test('the all() method returns the whole config', function () {
    Config::setPath(helper_configFilePath('ppq.php'));

    $configData = Config::all();

    expect($configData)->toBeArray();

    expect($configData)->toHaveKey('datapath');

    expect($configData['datapath'])->toEndWith('/datapath');

    expect($configData['driver'])->toBe(SimpleInMemoryDriver::class);

    expect($configData['bootstrap_file'])->toContain('bootstrap.php');

    expect($configData['queues'])->toBe([
        'default' => [
            'concurrent_jobs' => 6,
        ],
        'other_queue' => [
            'concurrent_jobs' => 3,
        ],
    ]);

    expect($configData['scheduler'])->toBe([
        'class' => Scheduler::class,
        'active' => true,
    ]);
});
