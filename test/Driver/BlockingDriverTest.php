<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\BlockingDriver;
use Amp\File\Test\DriverTest;

class BlockingDriverTest extends DriverTest
{
    protected function createDriver(): File\Driver
    {
        return new BlockingDriver;
    }
}
