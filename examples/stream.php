<?php

use Amp\File;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\pipe;

require __DIR__ . '/../vendor/autoload.php';

$tempFile = tempnam(sys_get_temp_dir(), 'test-');
if ($tempFile === false) {
    throw new RuntimeException('Failed to create temporary file');
}

print "Writing stdin to $tempFile" . PHP_EOL;

$file = File\openFile($tempFile, 'w');

pipe(getStdin(), $file);
