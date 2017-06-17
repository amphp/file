<?php

namespace Amp\File\Test;

class BlockingHandleTest extends HandleTest {
    protected function execute(callable $cb) {
        \Amp\Loop::run(function () use ($cb) {
            \Amp\File\filesystem(new \Amp\File\BlockingDriver);
            \Amp\Promise\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
