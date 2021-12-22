<?php

namespace Amp\File\Driver;

use Amp\File\Driver;
use Amp\File\File;
use Amp\File\FilesystemException;
use Amp\File\Internal;
use Amp\Future;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\TaskFailureThrowable;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerException;
use function Amp\async;
use function Amp\Parallel\Worker\pool;

final class ParallelDriver implements Driver
{
    public const DEFAULT_WORKER_LIMIT = 8;

    private Pool $pool;

    /** @var int Maximum number of workers to use for open files. */
    private int $workerLimit;

    /** @var \SplObjectStorage Worker storage. */
    private \SplObjectStorage $workerStorage;

    /** @var Future Pending worker request promise. */
    private Future $pendingWorker;

    /**
     * @param Pool|null $pool
     * @param int       $workerLimit Maximum number of workers to use from the pool for open files.
     */
    public function __construct(Pool $pool = null, int $workerLimit = self::DEFAULT_WORKER_LIMIT)
    {
        $this->pool = $pool ?? pool();
        $this->workerLimit = $workerLimit;
        $this->workerStorage = new \SplObjectStorage;
        $this->pendingWorker = Future::complete();
    }

    public function openFile(string $path, string $mode): File
    {
        $worker = $this->selectWorker();

        $workerStorage = $this->workerStorage;
        $worker = new Internal\FileWorker($worker, static function (Worker $worker) use ($workerStorage): void {
            \assert($workerStorage->contains($worker));
            if (($workerStorage[$worker] -=1) === 0 || !$worker->isRunning()) {
                $workerStorage->detach($worker);
            }
        });

        try {
            [$id, $size, $mode] = $worker->execute(new Internal\FileTask("fopen", [$path, $mode]));
        } catch (TaskFailureThrowable $exception) {
            throw new FilesystemException("Could not open file", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Could not send open request to worker", $exception);
        }

        return new ParallelFile($worker, $id, $path, $size, $mode);
    }

    private function selectWorker(): Worker
    {
        $this->pendingWorker->await(); // Wait for any currently pending request for a worker.

        if ($this->workerStorage->count() < $this->workerLimit) {
            $this->pendingWorker = async(fn() => $this->pool->getWorker());
            $worker = $this->pendingWorker->await();

            if ($this->workerStorage->contains($worker)) {
                // amphp/parallel v1.x may return an already used worker from the pool.
                $this->workerStorage[$worker] += 1;
            } else {
                // amphp/parallel v2.x should always return an unused worker.
                $this->workerStorage->attach($worker, 1);
            }

            return $worker;
        }

        $max = \PHP_INT_MAX;
        foreach ($this->workerStorage as $storedWorker) {
            $count = $this->workerStorage[$storedWorker];
            if ($count <= $max) {
                $worker = $storedWorker;
                $max = $count;
            }
        }

        \assert(isset($worker) && $worker instanceof Worker);

        if (!$worker->isRunning()) {
            $this->workerStorage->detach($worker);
            return $this->selectWorker();
        }

        $this->workerStorage[$worker] += 1;

        return $worker;
    }

    public function deleteFile(string $path): void
    {
        $this->runFileTask(new Internal\FileTask("deleteFile", [$path]));
    }

    public function getStatus(string $path): ?array
    {
        return $this->runFileTask(new Internal\FileTask("getStatus", [$path]));
    }

    public function move(string $from, string $to): void
    {
        $this->runFileTask(new Internal\FileTask("move", [$from, $to]));
    }

    public function createHardlink(string $target, string $link): void
    {
        $this->runFileTask(new Internal\FileTask("createHardlink", [$target, $link]));
    }

    public function createSymlink(string $target, string $link): void
    {
        $this->runFileTask(new Internal\FileTask("createSymlink", [$target, $link]));
    }

    public function resolveSymlink(string $path): string
    {
        return $this->runFileTask(new Internal\FileTask("resolveSymlink", [$path]));
    }

    public function createDirectory(string $path, int $mode = 0777): void
    {
        $this->runFileTask(new Internal\FileTask("createDirectory", [$path, $mode]));
    }

    public function createDirectoryRecursively(string $path, int $mode = 0777): void
    {
        $this->runFileTask(new Internal\FileTask("createDirectoryRecursively", [$path, $mode]));
    }

    public function listFiles(string $path): array
    {
        return $this->runFileTask(new Internal\FileTask("listFiles", [$path]));
    }

    public function deleteDirectory(string $path): void
    {
        $this->runFileTask(new Internal\FileTask("deleteDirectory", [$path]));
    }

    public function changePermissions(string $path, int $mode): void
    {
        $this->runFileTask(new Internal\FileTask("changePermissions", [$path, $mode]));
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): void
    {
        $this->runFileTask(new Internal\FileTask("changeOwner", [$path, $uid, $gid]));
    }

    public function getLinkStatus(string $path): ?array
    {
        return $this->runFileTask(new Internal\FileTask("getLinkStatus", [$path]));
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): void
    {
        $this->runFileTask(new Internal\FileTask(
            "touch",
            [$path, $modificationTime, $accessTime]
        ));
    }

    public function read(string $path): string
    {
        return $this->runFileTask(new Internal\FileTask("read", [$path]));
    }

    public function write(string $path, string $contents): void
    {
        $this->runFileTask(new Internal\FileTask("write", [$path, $contents]));
    }

    private function runFileTask(Internal\FileTask $task): mixed
    {
        try {
            return $this->pool->enqueue($task)->getFuture()->await();
        } catch (TaskFailureThrowable $exception) {
            throw new FilesystemException("The file operation failed", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Could not send the file task to worker", $exception);
        }
    }
}
