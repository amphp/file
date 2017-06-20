<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;
use function Amp\asyncCall;

class UvDriverTest extends DriverTest {
    protected function execute(callable $cb) {
        if (!\extension_loaded("uv")) {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }

        $loop = new Loop\UvDriver;

        Loop::set($loop);
        Loop::run(function () use ($cb, $loop) {
            File\filesystem(new File\UvDriver($loop));
            asyncCall($cb);
        });
    }
}
