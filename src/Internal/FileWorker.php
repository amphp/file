<?php declare(strict_types=1);

namespace Amp\File\Internal;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerPool;

/** @internal */
final class FileWorker
{
    /**
     * The pool of the worker, kept alive while the file is open: a pool created by the driver would otherwise kill
     * the worker, closing the file, once the driver is destroyed.
     *
     * @psalm-suppress UnusedProperty Only kept alive.
     */
    private readonly WorkerPool $pool;

    /**
     * @param \Closure(Worker):void $push Closure to push the worker back into the queue.
     * @param WorkerPool $pool The pool of the worker.
     */
    public function __construct(
        private readonly Worker $worker,
        private readonly \Closure $push,
        WorkerPool $pool,
    ) {
        $this->pool = $pool;
    }

    /**
     * Automatically pushes the worker back into the queue.
     */
    public function __destruct()
    {
        ($this->push)($this->worker);
    }

    public function isRunning(): bool
    {
        return $this->worker->isRunning();
    }

    public function isIdle(): bool
    {
        return $this->worker->isIdle();
    }

    public function execute(Task $task, ?Cancellation $cancellation = null): mixed
    {
        return $this->worker->submit($task, $cancellation)->await();
    }

    public function shutdown(): void
    {
        $this->worker->shutdown();
    }

    public function kill(): void
    {
        $this->worker->kill();
    }
}
