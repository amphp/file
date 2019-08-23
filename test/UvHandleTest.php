<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;

class UvHandleTest extends AsyncHandleTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded("uv")) {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }

        $loop = new Loop\UvDriver;
        Loop::set($loop);
        File\filesystem(new File\UvDriver($loop));
    }
}
