<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\EioDriver;
use Amp\File\Test\DriverTest;
use Amp\Loop;

class EioDriverTest extends DriverTest
{
    protected function createDriver(): File\Driver
    {
        if (!\extension_loaded("eio")) {
            $this->markTestSkipped("eio extension not loaded");
        }

        return new EioDriver(Loop::get());
    }
}
