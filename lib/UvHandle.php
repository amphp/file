<?php

namespace Amp\File;

use Amp\Deferred;
use Interop\Async\{ Awaitable, Loop\Driver };

class UvHandle implements Handle {
    const OP_READ = 1;
    const OP_WRITE = 2;

    private $busy;
    private $driver;
    private $fh;
    private $path;
    private $mode;
    private $size;
    private $loop;
    private $position;
    private $queue = [];
    private $pendingWriteOps = 0;
    private $isActive = false;
    private $isCloseInitialized = false;

    public function __construct(Driver $driver, $busy, $fh, $path, $mode, $size) {
        $this->driver = $driver;
        $this->busy = $busy;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;
        $this->loop = $driver->getHandle();
        $this->position = ($mode[0] === "a") ? $size : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $readLen): Awaitable {
        $deferred = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_READ;
        $op->position = $this->position;
        $op->promisor = $deferred;
        $op->readLen = $readLen;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->isActive = true;
            $this->doRead($op);
        }

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $writeData): Awaitable {
        $this->pendingWriteOps++;
        $deferred = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_WRITE;
        $op->position = $this->position;
        $op->promisor = $deferred;
        $op->writeData = $writeData;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->isActive = true;
            $this->doWrite($op);
        }

        return $deferred->getAwaitable();
    }

    private function doRead($op) {
        $this->driver->reference($this->busy);
        $onRead = function ($fh, $result, $buffer) use ($op) {
            $this->isActive = false;
            $this->driver->unreference($this->busy);
            if ($result < 0) {
                $op->promisor->fail(new FilesystemException(
                    \uv_strerror($result)
                ));
            } else {
                $this->position = $op->position + strlen($buffer);
                $op->promisor->resolve($buffer);
            }
            if ($this->queue) {
                $this->dequeue();
            }
        };
        \uv_fs_read($this->loop, $this->fh, $op->position, $op->readLen, $onRead);
    }

    private function doWrite($op) {
        $this->driver->reference($this->busy);
        $onWrite = function ($fh, $result) use ($op) {
            $this->isActive = false;
            $this->driver->unreference($this->busy);
            if ($result < 0) {
                $op->promisor->fail(new FilesystemException(
                    \uv_strerror($result)
                ));
            } else {
                StatCache::clear($this->path);
                $bytesWritten = \strlen($op->writeData);
                $this->pendingWriteOps--;
                $newPosition = $op->position + $bytesWritten;
                $delta = $newPosition - $this->position;
                $this->position = ($this->mode[0] === "a") ? $this->position : $newPosition;
                $this->size += $delta;
                $op->promisor->resolve($result);
            }
            if ($this->queue) {
                $this->dequeue();
            }
        };
        \uv_fs_write($this->loop, $this->fh, $op->writeData, $op->position, $onWrite);
    }

    private function dequeue() {
        $this->isActive = true;
        $op = \array_shift($this->queue);
        switch ($op->type) {
            case self::OP_READ: $this->doRead($op); break;
            case self::OP_WRITE: $this->doWrite($op); break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): Awaitable {
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
                throw new FilesystemException(
                    "Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected"
                );
        }
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
        return ($this->pendingWriteOps > 0) ? false : ($this->size <= $this->position);
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
    public function close(): Awaitable {
        $this->isCloseInitialized = true;
        $this->driver->reference($this->busy);
        $deferred = new Deferred;
        \uv_fs_close($this->loop, $this->fh, function($fh) use ($deferred) {
            $this->driver->unreference($this->busy);
            $deferred->resolve();
        });

        return $deferred->getAwaitable();
    }

    public function __destruct() {
        if (empty($this->isCloseInitialized)) {
            $this->close();
        }
    }
}
