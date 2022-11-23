<?php

use Otsch\Ppq\Processes;

it('checks if a running process with a certain pid exists', function () {
    $process = \Symfony\Component\Process\Process::fromShellCommandline(
        'php ' . __DIR__ . '/_testdata/scripts/do-something.php'
    );

    $process->start();

    $pid = $process->getPid();

    expect($pid)->toBeInt()->toBeGreaterThan(0);

    /** @var int $pid */

    expect(Processes::pidStillExists($pid))->toBeTrue();

    $process->stop(0);

    expect(Processes::pidStillExists($pid))->toBeFalse();
});

it('checks if a running process containing certain strings (in command) exists', function () {
    expect(Processes::processContainingStringsExists(['counting.php']))->toBeFalse();

    $process = \Symfony\Component\Process\Process::fromShellCommandline(
        'php ' . __DIR__ . '/_testdata/scripts/counting.php'
    );

    $process->start();

    expect(Processes::processContainingStringsExists(['counting.php']))->toBeTrue();
});

test('checking if a running process containing certain strings exists, exclude the current process', function () {
    $process = \Symfony\Component\Process\Process::fromShellCommandline(
        'php ' . __DIR__ . '/_testdata/scripts/check-process-already-running.php'
    );

    $process->run();

    expect($process->getOutput())->toBe('no');
});

it(
    'tells if a process is a child of another process',
    function (int $pid, string $command, int $parentPid, string $parentCommand, bool $expectedResult) {
        expect(Processes::isSubProcessOf($pid, $command, $parentPid, $parentCommand))->toBe($expectedResult);
    }
)->with([
    [123, 'php yolo.php', 121, 'sh -c php yolo.php', true],
    [123, 'php yolo.php', 121, 'php foo.php', false],
    [123, 'php vendor/bin/ppq list', 125, 'sh -c php vendor/bin/ppq logs 123.abc', false],
    [123, 'php foo.php', 122, 'sh -c php foo.php', true],
    [123, 'php foo.php', 125, 'sh -c php foo.php', true],
    [123, 'php foo.php', 126, 'sh -c php foo.php', false],
    [125, 'sh -c php foo.php', 123, 'php foo.php', false],
]);

it(
    'tells if one process is parent or child of another process',
    function (int $pid, string $command, int $otherPid, string $otherCommand, bool $expectedResult) {
        expect(Processes::oneIsSubProcessOfTheOther($pid, $command, $otherPid, $otherCommand))->toBe($expectedResult);
    }
)->with([
    [123, 'php yolo.php', 121, 'sh -c php yolo.php', true],
    [121, 'sh -c php yolo.php', 123, 'php yolo.php', true],
    [123, 'php yolo.php', 121, 'php foo.php', false],
]);
