<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

class BlockingHandleTest extends HandleTest {
    protected function lRun(callable $cb) {
        \Interop\Async\Loop::execute(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\BlockingDriver);
            \Amp\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
