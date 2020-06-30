<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\BlockingDriver;
use Amp\File\Test\FileTest;

class BlockingFileTest extends FileTest
{
    protected function createDriver(): File\Driver
    {
        return new BlockingDriver;
    }
}
