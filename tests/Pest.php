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

function helper_cleanUpDataPathQueueFiles(): void
{
    if (file_exists(__DIR__ . '/_testdata/datapath/index')) {
        file_put_contents(__DIR__ . '/_testdata/datapath/index', 'a:0:{}');
    }

    if (file_exists(__DIR__ . '/_testdata/datapath/queue-default')) {
        file_put_contents(__DIR__ . '/_testdata/datapath/queue-default', 'a:0:{}');
    }

    if (file_exists(__DIR__ . '/_testdata/datapath/queue-other_queue')) {
        file_put_contents(__DIR__ . '/_testdata/datapath/queue-other_queue', 'a:0:{}');
    }

    if (file_exists(__DIR__ . '/_testdata/datapath/queue-infinite_waiting_jobs_queue')) {
        file_put_contents(__DIR__ . '/_testdata/datapath/queue-infinite_waiting_jobs_queue', 'a:0:{}');
    }

    // clean up logs
    if (file_exists(__DIR__ . '/_testdata/datapath/logs')) {
        $queues = ['default', 'other_queue', 'infinite_waiting_jobs_queue'];

        foreach ($queues as $queue) {
            if (file_exists(__DIR__ . '/_testdata/datapath/logs/' . $queue)) {
                $filesInDir = scandir(__DIR__ . '/_testdata/datapath/logs/' . $queue);

                if (is_array($filesInDir)) {
                    foreach ($filesInDir as $file) {
                        if (str_ends_with($file, '.log')) {
                            unlink(__DIR__ . '/_testdata/datapath/logs/' . $queue . '/' . $file);
                        }
                    }
                }
            }
        }
    }
}

function helper_tryUntil(Closure $callback, mixed $arg = null, int $maxTries = 100, int $sleep = 10000): mixed
{
    $tries = 0;

    while (!($callbackReturnValue = $callback($arg)) && $tries < $maxTries) {
        usleep($sleep);

        $tries++;
    }

    return $callbackReturnValue;
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
