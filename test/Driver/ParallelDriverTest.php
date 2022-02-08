<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\ParallelDriver;
use Amp\File\Test\DriverTest;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\DefaultWorkerPool;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\WorkerPool;

class ParallelDriverTest extends DriverTest
{
    private WorkerPool $pool;

    protected function createDriver(): File\Driver
    {
        $this->pool = new DefaultWorkerPool();

        return new ParallelDriver($this->pool);
    }

    protected function tearDownAsync(): void
    {
        $this->pool->shutdown();
    }
}
