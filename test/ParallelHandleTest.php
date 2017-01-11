<?php

namespace Amp\File\Test;

use Amp\Parallel\Worker\DefaultPool;

class ParallelHandleTest extends HandleTest {
    protected function lRun(callable $cb) {
        \AsyncInterop\Loop::execute(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\ParallelDriver(new DefaultPool));
            \Amp\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
