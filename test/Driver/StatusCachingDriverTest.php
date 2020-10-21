<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\BlockingDriver;
use Amp\File\Test\DriverTest;

class StatusCachingDriverTest extends DriverTest
{
    protected function createDriver(): File\Driver
    {
        return new File\Driver\StatusCachingDriver(new BlockingDriver);
    }
}
