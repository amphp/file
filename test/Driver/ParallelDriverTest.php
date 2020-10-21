<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\ParallelDriver;
use Amp\File\Test\DriverTest;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;

class ParallelDriverTest extends DriverTest
{
    /** @var Pool */
    private $pool;

    protected function createDriver(): File\Driver
    {
        $this->pool = new DefaultPool;

        return new ParallelDriver($this->pool);
    }

    protected function tearDownAsync(): void
    {
        $this->pool->shutdown();
    }
}
