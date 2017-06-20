<?php

namespace Amp\File;

use Amp\Deferred;
use Amp\File\Internal\UvPoll;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

class UvHandle implements Handle {
    private $poll;
    private $driver;
    private $fh;
    private $path;
    private $mode;
    private $size;
    private $loop;
    private $position;
    private $queue;
    private $isActive = false;
    private $writable = true;

    /**
     * @param \Amp\Loop\UvDriver $driver
     * @param UvPoll $poll Poll for keeping the loop active.
     * @param resource $fh File handle.
     * @param string $path
     * @param string $mode
     * @param int $size
     */
    public function __construct(Loop\UvDriver $driver, UvPoll $poll, $fh, string $path, string $mode, int $size) {
        $this->driver = $driver;
        $this->poll = $poll;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;
        $this->loop = $driver->getHandle();
        $this->position = ($mode[0] === "a") ? $size : 0;

        $this->queue = new \SplQueue;
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $this->isActive = true;

        $onRead = function ($fh, $result, $buffer) use ($deferred) {
            $this->isActive = false;

            if ($result < 0) {
                $deferred->fail(new FilesystemException(\uv_strerror($result)));
            } else {
                $length = strlen($buffer);
                $this->position = $this->position + $length;
                $deferred->resolve($length ? $buffer : null);
            }
        };

        \uv_fs_read($this->loop, $this->fh, $this->position, $length, $onRead);

        return $deferred->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): Promise {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new \Error("The file is no longer writable");
        }

        $this->isActive = true;

        if ($this->queue->isEmpty()) {
            $promise = $this->push($data);
        } else {
            $promise = $this->queue->top();
            $promise = call(function () use ($promise, $data) {
                yield $promise;
                return yield $this->push($data);
            });
        }

        $this->queue->push($promise);

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = ""): Promise {
        $promise = $this->write($data);
        $this->writable = false;
        $promise->onResolve([$this, "close"]);
        return $promise;
    }

    private function push(string $data): Promise {
        $length = \strlen($data);

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onWrite = function ($fh, $result) use ($deferred, $length) {
            if ($this->queue->isEmpty()) {
                $deferred->fail(new FilesystemException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result < 0) {
                $deferred->fail(new FilesystemException(
                    \uv_strerror($result)
                ));
            } else {
                StatCache::clear($this->path);
                $newPosition = $this->position + $length;
                $delta = $newPosition - $this->position;
                $this->position = ($this->mode[0] === "a") ? $this->position : $newPosition;
                $this->size += $delta;
                $deferred->resolve($length);
            }
        };

        \uv_fs_write($this->loop, $this->fh, $data, $this->position, $onWrite);

        return $deferred->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): Promise {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $offset = (int) $offset;
        switch ($whence) {
            case \SEEK_SET:
                $this->position = $offset;
                break;
            case \SEEK_CUR:
                $this->position = $this->position + $offset;
                break;
            case \SEEK_END:
                $this->position = $this->size + $offset;
                break;
            default:
                throw new \Error(
                    "Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected"
                );
        }

        return new Success($this->position);
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool {
        return !$this->queue->isEmpty() ? false : ($this->size <= $this->position);
    }

    /**
     * {@inheritdoc}
     */
    public function path(): string {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function mode(): string {
        return $this->mode;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Promise {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_close($this->loop, $this->fh, function ($fh) use ($deferred) {
            $deferred->resolve();
        });

        return $deferred->promise();
    }
}
