<?php

namespace Amp\File\Test;

use Amp\File;

class EioDriverTest extends DriverTest
{
    protected function createDriver(): File\Driver
    {
        if (!\extension_loaded("eio")) {
            $this->markTestSkipped("eio extension not loaded");
        }

        return new File\EioDriver;
    }
}
