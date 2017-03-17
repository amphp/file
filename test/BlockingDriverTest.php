<?php

namespace Amp\File\Test;

class BlockingDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        \Amp\Loop::run(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\BlockingDriver);
            \Amp\Promise\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
