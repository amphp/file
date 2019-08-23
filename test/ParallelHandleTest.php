<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;

class ParallelHandleTest extends AsyncHandleTest
{
    /** @var Pool */
    private $pool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = new DefaultPool;
        File\filesystem(new File\ParallelDriver($this->pool));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->pool->shutdown();
    }
}
