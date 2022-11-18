<?php

use Otsch\Ppq\Process;

include 'vendor/autoload.php';

echo Process::runningPhpProcessContainingStringsExists(['check-process-already-running.php']) ? 'yes' : 'no';
