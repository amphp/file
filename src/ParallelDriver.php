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
    /**
     * @var \Amp\Parallel\Worker\Pool
     */
    private $pool;

    /**
     * @param \Amp\Parallel\Worker\Pool|null $pool
     */
    public function __construct(Pool $pool = null)
    {
        $this->pool = $pool ?: Worker\pool();
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): Promise
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

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("unlink", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise
    {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        return call(function () use ($path) {
            $stat = yield from $this->runFileTask(new Internal\FileTask("stat", [$path]));
            if (!empty($stat)) {
                StatCache::set($path, $stat);
            }
            return $stat;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("rename", [$from, $to])));
    }

    /**
     * {@inheritdoc}
     */
    public function link(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("link", [$target, $link])));
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("symlink", [$target, $link])));
    }

    /**
     * {@inheritdoc}
     */
    public function readlink(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("readlink", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0777, bool $recursive = false): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("mkdir", [$path, $mode, $recursive])));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("scandir", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("rmdir", [$path])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("chmod", [$path, $mode])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, ?int $uid, ?int $gid): Promise
    {
        $promise = new Coroutine($this->runFileTask(new Internal\FileTask("chown", [$path, $uid, $gid])));
        StatCache::clearOn($promise, $path);
        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function lstat(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("lstat", [$path])));
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
    public function get(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("get", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("put", [$path, $contents])));
    }
}
