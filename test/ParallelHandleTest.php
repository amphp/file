<?php

namespace Amp\File\Test;

use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;

class ParallelHandleTest extends HandleTest {
    /** @var \Amp\Parallel\Worker\Pool */
    private $pool;

    public function setUp() {
        $this->pool = new DefaultPool;
        $this->pool->start();
    }

    public function tearDown() {
        Loop::run(function () {
            yield $this->pool->shutdown();
        });
    }

    protected function lRun(callable $cb) {
        \Amp\Loop::run(function() use ($cb) {
            \Amp\File\filesystem(new \Amp\File\ParallelDriver($this->pool));
            \Amp\Promise\rethrow(new \Amp\Coroutine($cb()));
        });
    }
}
