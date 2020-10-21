<?php

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Deferred;
use Amp\Failure;
use Amp\File\File;
use Amp\File\Internal;
use Amp\File\PendingOperationError;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class EioFile implements File
{
    /** @var Internal\EioPoll */
    private $poll;

    /** @var resource eio file handle. */
    private $fh;

    /** @var string */
    private $path;

    /** @var string */
    private $mode;

    /** @var int */
    private $size;

    /** @var int */
    private $position;

    /** @var \SplQueue */
    private $queue;

    /** @var bool */
    private $isActive = false;

    /** @var bool */
    private $writable = true;

    /** @var Promise|null */
    private $closing;

    public function __construct(Internal\EioPoll $poll, $fh, string $path, string $mode, int $size)
    {
        $this->poll = $poll;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;
        $this->position = ($mode[0] === "a") ? $size : 0;

        $this->queue = new \SplQueue;
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $this->isActive = true;

        $remaining = $this->size - $this->position;
        $length = $length > $remaining ? $remaining : $length;

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onRead = function (Deferred $deferred, $result, $req): void {
            $this->isActive = false;

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->fail(new ClosedException("Reading from the file failed due to a closed handle"));
                } else {
                    $deferred->fail(new StreamException("Reading from the file failed:" . $error));
                }
            } else {
                $this->position += \strlen($result);
                $deferred->resolve(\strlen($result) ? $result : null);
            }
        };

        \eio_read(
            $this->fh,
            $length,
            $this->position,
            \EIO_PRI_DEFAULT,
            $onRead,
            $deferred
        );

        return $deferred->promise();
    }

    public function write(string $data): Promise
    {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
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

    public function end(string $data = ""): Promise
    {
        return call(function () use ($data) {
            $promise = $this->write($data);
            $this->writable = false;

            // ignore any errors
            yield Promise\any([$this->close()]);

            return $promise;
        });
    }

    public function close(): Promise
    {
        if ($this->closing) {
            return $this->closing;
        }

        $deferred = new Deferred;
        $this->poll->listen($this->closing = $deferred->promise());

        \eio_close($this->fh, \EIO_PRI_DEFAULT, static function (Deferred $deferred): void {
            // Ignore errors when closing file, as the handle will become invalid anyway.
            $deferred->resolve();
        }, $deferred);

        return $deferred->promise();
    }

    public function truncate(int $size): Promise
    {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            return new Failure(new ClosedException("The file is no longer writable"));
        }

        $this->isActive = true;

        if ($this->queue->isEmpty()) {
            $promise = $this->trim($size);
        } else {
            $promise = $this->queue->top();
            $promise = call(function () use ($promise, $size) {
                yield $promise;
                return yield $this->trim($size);
            });
        }

        $this->queue->push($promise);

        return $promise;
    }

    public function seek(int $offset, int $whence = \SEEK_SET): Promise
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        switch ($whence) {
            case self::SEEK_SET:
                $this->position = $offset;
                break;
            case self::SEEK_CUR:
                $this->position += $offset;
                break;
            case self::SEEK_END:
                $this->position = $this->size + $offset;
                break;
            default:
                throw new \Error("Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected");
        }

        return new Success($this->position);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return !$this->queue->isEmpty() ? false : ($this->size <= $this->position);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    private function push(string $data): Promise
    {
        $length = \strlen($data);

        if ($length === 0) {
            return new Success(0);
        }

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onWrite = function (Deferred $deferred, $result, $req): void {
            if ($this->queue->isEmpty()) {
                $deferred->fail(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->fail(new ClosedException("Writing to the file failed due to a closed handle"));
                } else {
                    $deferred->fail(new StreamException("Writing to the file failed: " . $error));
                }
            } else {
                $this->position += $result;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }

                $deferred->resolve($result);
            }
        };

        \eio_write(
            $this->fh,
            $data,
            $length,
            $this->position,
            \EIO_PRI_DEFAULT,
            $onWrite,
            $deferred
        );

        return $deferred->promise();
    }

    private function trim(int $size): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onTruncate = function (Deferred $deferred, $result, $req) use ($size): void {
            if ($this->queue->isEmpty()) {
                $deferred->fail(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->fail(new ClosedException("Truncating the file failed due to a closed handle"));
                } else {
                    $deferred->fail(new StreamException("Truncating the file failed: " . $error));
                }
            } else {
                $this->size = $size;
                $deferred->resolve();
            }
        };

        \eio_ftruncate(
            $this->fh,
            $size,
            \EIO_PRI_DEFAULT,
            $onTruncate,
            $deferred
        );

        return $deferred->promise();
    }
}
