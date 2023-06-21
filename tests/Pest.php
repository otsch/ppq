<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

//expect()->extend('toBeOne', function () {
//    return $this->toBe(1);
//});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function helper_testDataPath(string $withinPath = ''): string
{
    if (!empty($withinPath)) {
        $withinPath = str_starts_with($withinPath, '/') ? $withinPath : '/' . $withinPath;
    }

    return __DIR__ . '/_testdata/datapath' . $withinPath;
}

function helper_testConfigPath(string $withinPath = ''): string
{
    if (!empty($withinPath)) {
        $withinPath = str_starts_with($withinPath, '/') ? $withinPath : '/' . $withinPath;
    }

    return __DIR__ . '/_testdata/config' . $withinPath;
}

function helper_testScriptPath(string $withinPath = ''): string
{
    if (!empty($withinPath)) {
        $withinPath = str_starts_with($withinPath, '/') ? $withinPath : '/' . $withinPath;
    }

    return __DIR__ . '/_testdata/scripts' . $withinPath;
}

function helper_cleanUpDataPathQueueFiles(): void
{
    if (file_exists(helper_testDataPath('index'))) {
        file_put_contents(helper_testDataPath('index'), 'a:0:{}');
    }

    if (file_exists(helper_testDataPath('queue-default'))) {
        file_put_contents(helper_testDataPath('queue-default'), 'a:0:{}');
    }

    if (file_exists(helper_testDataPath('queue-other_queue'))) {
        file_put_contents(helper_testDataPath('queue-other_queue'), 'a:0:{}');
    }

    if (file_exists(helper_testDataPath('queue-infinite_waiting_jobs_queue'))) {
        file_put_contents(helper_testDataPath('queue-infinite_waiting_jobs_queue'), 'a:0:{}');
    }

    // clean up logs
    if (file_exists(helper_testDataPath('logs'))) {
        $queues = ['default', 'other_queue', 'infinite_waiting_jobs_queue'];

        foreach ($queues as $queue) {
            if (file_exists(helper_testDataPath('logs/' . $queue))) {
                $filesInDir = scandir(helper_testDataPath('logs/' . $queue));

                if (is_array($filesInDir)) {
                    foreach ($filesInDir as $file) {
                        if (str_ends_with($file, '.log')) {
                            unlink(helper_testDataPath('logs/' . $queue . '/' . $file));
                        }
                    }
                }

                rmdir(helper_testDataPath('logs/' . $queue));
            }
        }

        if (file_exists(helper_testDataPath('logs'))) {
            rmdir(helper_testDataPath('logs'));
        }
    }
}

/**
 * @param string[] $contains
 */
function helper_containsInOneLine(string $string, array $contains): bool
{
    foreach (explode(PHP_EOL, $string) as $line) {
        if (empty($line)) {
            continue;
        }

        foreach ($contains as $containsElement) {
            if (!str_contains($line, $containsElement)) {
                continue 2;
            }
        }

        return true;
    }

    return false;
};
