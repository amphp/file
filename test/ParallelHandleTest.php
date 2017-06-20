<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use function Amp\call;

class ParallelHandleTest extends AsyncHandleTest {
    protected function execute(callable $cb) {
        Loop::run(function () use ($cb) {
            $pool = new DefaultPool;
            $pool->start();

            File\filesystem(new File\ParallelDriver($pool));
            yield call($cb);

            yield $pool->shutdown();
        });
    }

    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testSimultaneousSeeks() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->seek(0);
            $promise2 = $handle->seek(10);

            $this->assertSame(0, yield $promise1);

            yield $promise2;
        });
    }

    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testReadWhileSeeking() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->seek(0);
            $promise2 = $handle->read();

            $this->assertSame(0, yield $promise1);

            yield $promise2;
        });
    }
}
