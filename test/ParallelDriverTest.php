<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;
use Amp\Parallel\Worker\DefaultPool;
use function Amp\call;

class ParallelDriverTest extends DriverTest {
    protected function lRun(callable $cb) {
        Loop::run(function() use ($cb) {
            $pool = new DefaultPool;
            $pool->start();

            File\filesystem(new File\ParallelDriver($pool));
            yield call($cb);

            yield $pool->shutdown();
        });
    }
}
