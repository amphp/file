<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;

class ParallelHandleTest extends AsyncFileTest
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

    public function testMultipleOpenFiles(): \Generator
    {
        $maxCount = File\ParallelDriver::DEFAULT_WORKER_LIMIT;

        $files = [];
        for ($i = 0; $i < $maxCount * 3; ++$i) {
            $files[] = yield File\open(__FILE__, 'r');
        }

        try {
            $this->assertSame($maxCount, $this->pool->getWorkerCount());
        } finally {
            foreach ($files as $file) {
                $file->close();
            }
        }
    }
}
