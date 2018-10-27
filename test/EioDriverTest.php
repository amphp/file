<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;
use function Amp\asyncCall;

class EioDriverTest extends DriverTest
{
    protected function execute(callable $cb)
    {
        if (!\extension_loaded("eio")) {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }

        Loop::run(function () use ($cb) {
            File\filesystem(new File\EioDriver);
            asyncCall($cb);
        });
    }
}
