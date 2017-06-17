<?php

namespace Amp\File\Test;

class EioDriverTest extends DriverTest {
    protected function execute(callable $cb) {
        if (\extension_loaded("eio")) {
            \Amp\Loop::run(function () use ($cb) {
                \Amp\File\filesystem(new \Amp\File\EioDriver);
                \Amp\Promise\rethrow(new \Amp\Coroutine($cb()));
            });
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
}
