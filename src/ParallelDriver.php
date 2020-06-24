<?php

namespace Amp\File;

use Amp\Coroutine;
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function deleteFile(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("deleteFile", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("move", [$from, $to])));
    }

    /**
     * {@inheritdoc}
     */
    public function createHardlink(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createHardlink", [$target, $link])));
    }

    /**
     * {@inheritdoc}
     */
    public function createSymlink(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createSymlink", [$target, $link])));
    }

    /**
     * {@inheritdoc}
     */
    public function resolveSymlink(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("resolveSymlink", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path, int $mode = 0777, bool $recursive = false): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("createDirectory", [$path, $mode, $recursive])));
    }

    /**
     * {@inheritdoc}
     */
    public function listFiles(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("listFiles", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("deleteDirectory", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function changePermissions(string $path, int $mode): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("changePermissions", [$path, $mode])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("changeOwner", [$path, $uid, $gid])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function getLinkStatus(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("getLinkStatus", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function touch(string $path, ?int $time, ?int $atime): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("touch", [$path, $time, $atime])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("read", [$path])));
    }

    /**
     * {@inheritdoc}
     */
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
