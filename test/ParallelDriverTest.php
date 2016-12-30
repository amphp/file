<?php

namespace Amp\File\Test;

use Amp\Parallel\Worker\DefaultPool;

class ParallelDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        \Interop\Async\Loop::execute(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\ParallelDriver(new DefaultPool));
            \Amp\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
