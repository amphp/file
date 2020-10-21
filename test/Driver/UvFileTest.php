<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\UvDriver;
use Amp\File\Test\AsyncFileTest;
use Amp\Loop;

class UvFileTest extends AsyncFileTest
{
    protected function createDriver(): File\Driver
    {
        if (!\extension_loaded("uv")) {
            $this->markTestSkipped("php-uv extension not loaded");
        }

        $loop = new Loop\UvDriver;
        Loop::set($loop);

        return new UvDriver($loop);
    }
}
