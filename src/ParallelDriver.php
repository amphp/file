<?php

namespace Amp\File;

use Amp\Coroutine;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\WorkerException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\Parallel\Worker\pool;

final class ParallelDriver implements Driver
{
    /** @var int Default maximum number of workers to use for open files. Opening more files will reuse workers. */
    public const DEFAULT_WORKER_LIMIT = 8;

    /** @var Pool */
    private $pool;

    /** @var int Maximum number of workers to use for open files. */
    private $workerLimit;

    /** @var \SplObjectStorage Worker storage. */
    private $workerStorage;

    /** @var Promise Pending worker request promise. */
    private $pendingWorker;

    /**
     * @param Pool|null $pool        If null, the default pool defined by {@see pool()} is used.
     * @param int       $workerLimit Maximum number of workers to use from the pool for open files.
     */
    public function __construct(Pool $pool = null, int $workerLimit = self::DEFAULT_WORKER_LIMIT)
    {
        $this->pool = $pool ?: pool();
        $this->workerLimit = $workerLimit;
        $this->workerStorage = new \SplObjectStorage;
        $this->pendingWorker = new Success;
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): Promise
    {
        return call(function () use ($path, $mode): \Generator {
            $worker = yield from $this->selectWorker();
            \assert($worker instanceof Worker);

            $workerStorage = $this->workerStorage;
            $worker = new Internal\FileWorker($worker, static function (Worker $worker) use ($workerStorage): void {
                \assert($workerStorage->contains($worker));
                if (($workerStorage[$worker] -=1) === 0 || !$worker->isRunning()) {
                    $workerStorage->detach($worker);
                }
            });

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

    private function selectWorker(): \Generator
    {
        yield $this->pendingWorker; // Wait for any currently pending request for a worker.

        if ($this->workerStorage->count() < $this->workerLimit) {
            $worker = $this->pool->getWorker();
            if ($worker instanceof Promise) { // amphp/parallel v1.x returns a Worker instead of a Promise.
                $this->pendingWorker = $worker;
                $worker = yield $worker;
            }

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
            return yield from $this->selectWorker();
        }

        $this->workerStorage[$worker] += 1;

        return $worker;
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
        return call(function () use ($path): \Generator {
            $result = yield from $this->runFileTask(new Internal\FileTask("unlink", [$path]));
            StatCache::clear($path);
            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise
    {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        return call(function () use ($path): \Generator {
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
    public function isfile(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                return false;
            }
            if ($stat["mode"] & 0100000) {
                return true;
            }
            return false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isdir(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                return false;
            }
            if ($stat["mode"] & 0040000) {
                return true;
            }
            return false;
        });
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
        return call(function () use ($path): \Generator {
            $result = yield from $this->runFileTask(new Internal\FileTask("rmdir", [$path]));
            StatCache::clear($path);
            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("chmod", [$path, $mode])));
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid, int $gid): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("chown", [$path, $uid, $gid])));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("exists", [$path])));
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            if ($stat["mode"] & 0100000) {
                return $stat["size"];
            }
            throw new FilesystemException("Specified path is not a regular file");
        });
    }

    /**
     * {@inheritdoc}
     */
    public function mtime(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["mtime"];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function atime(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["atime"];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function ctime(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["ctime"];
        });
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
    public function touch(string $path, int $time = null, int $atime = null): Promise
    {
        return new Coroutine($this->runFileTask(new Internal\FileTask("touch", [$path, $time, $atime])));
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
