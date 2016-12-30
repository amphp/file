<?php

namespace Amp\File\Test;

class EioDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        if (\extension_loaded("eio")) {
            \Interop\Async\Loop::execute(function() use ($cb) {
                \Amp\File\filesystem(new \Amp\File\EioDriver);
                \Amp\rethrow(new \Amp\Coroutine($cb()));
            });
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
}
