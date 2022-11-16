<?php

namespace Entities\Values;

use Exception;
use Otsch\Ppq\Entities\Values\QueueJobStatus;

it('can be created from string', function (string $string, QueueJobStatus $queueJobStatus) {
    expect(QueueJobStatus::fromString($string))->toBe($queueJobStatus);
})->with([
    ['waiting', QueueJobStatus::waiting],
    ['running', QueueJobStatus::running],
    ['finished', QueueJobStatus::finished],
    ['failed', QueueJobStatus::failed],
    ['lost', QueueJobStatus::lost],
]);

it('throws an exception when a string doesn\'t match a QueueJobStatus', function () {
    QueueJobStatus::fromString('unknown');
})->throws(Exception::class);

it('tells if a status means that a job is already in the past (not waiting or running)', function (QueueJobStatus $status, bool $isPast) {
    expect($status->isPast())->toBe($isPast);
})->with([
    [QueueJobStatus::waiting, false],
    [QueueJobStatus::running, false],
    [QueueJobStatus::finished, true],
    [QueueJobStatus::failed, true],
    [QueueJobStatus::lost, true],
]);
