<?php declare(strict_types = 1);

namespace Amp\File\Test;

class UvDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        if (\extension_loaded("uv")) {
            $loop = new \Amp\Loop\UvLoop;
            \Interop\Async\Loop::execute(function() use ($cb, $loop) {
                \Amp\File\filesystem(new \Amp\File\UvDriver($loop));
                \Amp\rethrow(new \Amp\Coroutine($cb()));
            }, $loop);
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }
}
