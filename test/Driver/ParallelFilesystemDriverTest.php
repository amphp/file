<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\ParallelFilesystemDriver;
use Amp\File\Test\FilesystemDriverTest;
use Amp\Parallel\Worker\DefaultWorkerPool;
use Amp\Parallel\Worker\WorkerPool;

class ParallelFilesystemDriverTest extends FilesystemDriverTest
{
    private WorkerPool $pool;

    protected function createDriver(): File\FilesystemDriver
    {
        $this->pool = new DefaultWorkerPool();

        return new ParallelFilesystemDriver($this->pool);
    }

    protected function tearDownAsync(): void
    {
        $this->pool->shutdown();
    }
}
