<?php

use Otsch\Ppq\Processes;

include 'vendor/autoload.php';

echo Processes::processContainingStringsExists(['check-process-already-running.php']) ? 'yes' : 'no';
