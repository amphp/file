<?php

namespace Amp\File\Test;

class BlockingDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        \AsyncInterop\Loop::execute(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\BlockingDriver);
            \Amp\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
