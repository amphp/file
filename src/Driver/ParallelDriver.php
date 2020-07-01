<?php

namespace Amp\File\Driver;

use Amp\Coroutine;
use Amp\File\Driver;
use Amp\File\FilesystemException;
use Amp\File\Internal;
use Amp\File\StatCache;
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\WorkerException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class ParallelDriver implements Driver
{
    /** @var Pool */
    private $pool;

    /**
     * @param Pool|null $pool
     */
    public function __construct(Pool $pool = null)
    {
        $this->pool = $pool ?: Worker\pool();
    }

    public function openFile(string $path, string $mode): Promise
    {
        return call(function () use ($path, $mode) {
            $worker = $this->pool->getWorker();
            try {
                [$id, $size, $mode] = yield $worker->enqueue(new Internal\FileTask("fopen", [$path, $mode]));
            } catch (TaskException $exception) {
                throw new FilesystemException("Could not open file", $exception);
            } catch (WorkerException $exception) {
                throw new FilesystemException("Could not send open request to worker", $exception);
            }
            return new ParallelFile($worker, $id, $path, $size, $mode);
        });
    }

    public function deleteFile(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("deleteFile", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    public function getStatus(string $path): Promise
    {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        return call(function () use ($path) {
            $stat = yield from $this->runFileTask(new Internal\FileTask("getStatus", [$path]));
            if (!empty($stat)) {
                StatCache::set($path, $stat);
            }
            return $stat;
        });
    }

    public function move(string $from, string $to): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("move", [$from, $to])));
    }

    public function createHardlink(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createHardlink", [$target, $link])));
    }

    public function createSymlink(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createSymlink", [$target, $link])));
    }

    public function resolveSymlink(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("resolveSymlink", [$path])));
    }

    public function createDirectory(string $path, int $mode = 0777): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createDirectory", [$path, $mode])));
    }

    public function createDirectories(string $path, int $mode = 0777): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createDirectories", [$path, $mode])));
    }

    public function listFiles(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("listFiles", [$path])));
    }

    public function deleteDirectory(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("deleteDirectory", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    public function changePermissions(string $path, int $mode): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("changePermissions", [$path, $mode])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("changeOwner", [$path, $uid, $gid])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    public function getLinkStatus(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("getLinkStatus", [$path])));
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask(
            "touch",
            [$path, $modificationTime, $accessTime]
        )));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    public function read(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("read", [$path])));
    }

    public function write(string $path, string $contents): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("write", [$path, $contents])));
    }

    private function runFileTask(Internal\FileTask $task): \Generator
    {
        try {
            return yield $this->pool->enqueue($task);
        } catch (TaskException $exception) {
            throw new FilesystemException("The file operation failed", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Could not send the file task to worker", $exception);
        }
    }
}
