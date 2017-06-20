<?php

namespace Amp\File;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;

class EioHandle implements Handle {
    const OP_READ = 1;
    const OP_WRITE = 2;

    private $poll;
    private $fh;
    private $path;
    private $mode;
    private $size;
    private $position;
    private $queue = [];
    private $pendingWriteOps = 0;
    private $isActive = false;

    public function __construct(Internal\EioPoll $poll, $fh, string $path, string $mode, int $size) {
        $this->poll = $poll;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;
        $this->position = ($mode[0] === "a") ? $size : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise {
        $deferred = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_READ;
        $op->position = $this->position;
        $op->deferred = $deferred;
        $remaining = $this->size - $this->position;
        $op->readLen = ($length > $remaining) ? $remaining : $length;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->poll->listen();
            $this->isActive = true;
            \eio_read($this->fh, $op->readLen, $op->position, \EIO_PRI_DEFAULT, [$this, "onRead"], $op);
        }

        return $deferred->promise();
    }

    private function dequeue() {
        $this->isActive = true;
        $op = \array_shift($this->queue);
        switch ($op->type) {
            case self::OP_READ:
                $this->poll->listen();
                $this->isActive = true;
                \eio_read($this->fh, $op->readLen, $op->position, \EIO_PRI_DEFAULT, [$this, "onRead"], $op);
                break;
            case self::OP_WRITE:
                $this->poll->listen();
                $this->isActive = true;
                \eio_write($this->fh, $op->writeData, \strlen($op->writeData), $op->position, \EIO_PRI_DEFAULT, [$this, "onWrite"], $op);
                break;
        }
    }

    private function onRead($op, $result, $req) {
        $this->isActive = false;
        $this->poll->done();
        if ($result === -1) {
            $op->deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $length = \strlen($result);
            $this->position = $op->position + $length;
            $op->deferred->resolve($length ? $result : null);
        }
        if ($this->queue) {
            $this->dequeue();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): Promise {
        $deferred = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_WRITE;
        $op->position = $this->position;
        $op->deferred = $deferred;
        $op->writeData = $data;
        $this->pendingWriteOps++;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->poll->listen();
            $this->isActive = true;
            \eio_write($this->fh, $data, \strlen($data), $op->position, \EIO_PRI_DEFAULT, [$this, "onWrite"], $op);
        }

        return $deferred->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = ""): Promise {
        $promise = $this->write($data);
        $promise->onResolve([$this, "close"]);
        return $promise;
    }

    private function onWrite($op, $result, $req) {
        $this->isActive = false;
        $this->poll->done();
        if ($result === -1) {
            $op->deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::clear($this->path);
            $bytesWritten = \strlen($op->writeData);
            $this->pendingWriteOps--;
            $newPosition = $op->position + $bytesWritten;
            $delta = $newPosition - $this->position;
            $this->position = ($this->mode[0] === "a") ? $this->position : $newPosition;
            $this->size += $delta;
            $op->deferred->resolve($result);
        }
        if ($this->queue) {
            $this->dequeue();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Promise {
        $this->poll->listen();
        $deferred = new Deferred;
        \eio_close($this->fh, \EIO_PRI_DEFAULT, [$this, "onClose"], $deferred);

        return $deferred->promise();
    }

    private function onClose($deferred, $result, $req) {
        $this->poll->done();
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $deferred->resolve();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): Promise {
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
}
