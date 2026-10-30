<?php declare(strict_types=1);

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\ParallelFilesystemDriver;
use Amp\File\Test\AsyncFileTest;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Worker\WorkerPool;
use function Amp\delay;

class ParallelFileTest extends AsyncFileTest
{
    private const DEFAULT_WORKER_LIMIT = 8;

    private WorkerPool $pool;

    protected function createDriver(int $workerLimit = self::DEFAULT_WORKER_LIMIT): File\FilesystemDriver
    {
        $this->pool = new ContextWorkerPool($workerLimit);

        return new ParallelFilesystemDriver($this->pool);
    }

    protected function tearDown(): void
    {
        $this->pool->shutdown();
    }

    public function getWorkerLimits(): iterable
    {
        return \array_map(fn (int $count): array => [$count], \range(4, 16, 4));
    }

    /**
     * @dataProvider getWorkerLimits
     */
    public function testMultipleOpenFiles(int $maxCount)
    {
        $driver = $this->createDriver($maxCount);

        $files = [];
        for ($i = 0; $i < $maxCount * 2; ++$i) {
            $files[] = $driver->openFile(__FILE__, 'r');
        }

        foreach ($files as $file) {
            $file->close();
        }

        $this->assertSame($maxCount, $this->pool->getWorkerCount());
    }

    public function testOpenFileKeepsTheDriversPoolAlive(): void
    {
        // The driver creates and owns its pool: the file must keep it, and the worker holding the file, alive.
        $driver = new ParallelFilesystemDriver();
        $file = $driver->openFile(__FILE__, 'r');
        unset($driver);
        \gc_collect_cycles();

        $this->assertSame(\file_get_contents(__FILE__, length: 5), $file->read(length: 5));
        $file->close();
    }

    public function testFileDestroyedBeforeItsWorkerShutsDown(): void
    {
        $file = $this->driver->openFile(__FILE__, 'r');

        // Destroying the file queues its close; the worker exits before the close is sent.
        unset($file);
        $this->pool->kill();

        delay(0.1);
        $this->assertFalse($this->pool->isRunning());
    }
}
