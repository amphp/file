<?php

namespace Amp\File\Test;

use Amp\Parallel\Worker\DefaultPool;

class ParallelDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        \Amp\Loop::run(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\ParallelDriver(new DefaultPool));
            \Amp\Promise\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
