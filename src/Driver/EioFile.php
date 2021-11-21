<?php

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\Deferred;
use Amp\File\File;
use Amp\File\Internal;
use Amp\File\PendingOperationError;
use Amp\Future;
use function Amp\launch;

final class EioFile implements File
{
    private Internal\EioPoll $poll;

    /** @var resource eio file handle. */
    private $fh;

    private string $path;

    private string $mode;

    private int $size;

    private int $position;

    private \SplQueue $queue;

    private bool $isActive = false;

    private bool $writable = true;

    private ?Future $closing = null;

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

    public function read(?CancellationToken $token = null, int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $this->isActive = true;

        $remaining = $this->size - $this->position;
        $length = $length > $remaining ? $remaining : $length;

        $deferred = new Deferred;
        $this->poll->listen();

        $onRead = function (Deferred $deferred, $result, $req): void {
            $this->isActive = false;

            if ($deferred->isComplete()) {
                return;
            }

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->error(new ClosedException("Reading from the file failed due to a closed handle"));
                } else {
                    $deferred->error(new StreamException("Reading from the file failed:" . $error));
                }
            } else {
                $this->position += \strlen($result);
                $deferred->complete(\strlen($result) ? $result : null);
            }
        };

        $request = \eio_read(
            $this->fh,
            $length,
            $this->position,
            \EIO_PRI_DEFAULT,
            $onRead,
            $deferred
        );

        $id = $token?->subscribe(function (\Throwable $exception) use ($request, $deferred): void {
            $this->isActive = false;
            $deferred->error($exception);
            \eio_cancel($request);
        });

        try {
            return $deferred->getFuture()->await();
        } finally {
            $token?->unsubscribe($id);
            $this->poll->done();
        }
    }

    public function write(string $data): Future
    {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
        }

        $this->isActive = true;

        if ($this->queue->isEmpty()) {
            $future = $this->push($data);
        } else {
            $future = $this->queue->top();
            $future = launch(function () use ($future, $data): void {
                $future->await();
                $this->push($data)->await();
            });
        }

        $this->queue->push($future);

        return $future;
    }

    public function end(string $data = ""): Future
    {
        return launch(function () use ($data): void {
            try {
                $future = $this->write($data);
                $this->writable = false;
                $future->await();
            } finally {
                $this->close();
            }
        });
    }

    public function close(): void
    {
        if ($this->closing) {
            $this->closing->await();
            return;
        }

        $deferred = new Deferred;
        $this->closing = $deferred->getFuture();
        $this->poll->listen();

        \eio_close($this->fh, \EIO_PRI_DEFAULT, static function (Deferred $deferred): void {
            // Ignore errors when closing file, as the handle will become invalid anyway.
            $deferred->complete(null);
        }, $deferred);

        try {
            $this->closing->await();
        } finally {
            $this->poll->done();
        }
    }

    public function truncate(int $size): void
    {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
        }

        $this->isActive = true;

        if ($this->queue->isEmpty()) {
            $future = $this->trim($size);
        } else {
            $future = $this->queue->top();
            $future = launch(function () use ($future, $size): void {
                $future->await();
                $this->trim($size)->await();
            });
        }

        $this->queue->push($future);

        $future->await();
    }

    public function seek(int $offset, int $whence = \SEEK_SET): int
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

        return $this->position;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function atEnd(): bool
    {
        return $this->queue->isEmpty() && $this->size <= $this->position;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    private function push(string $data): Future
    {
        $length = \strlen($data);

        if ($length === 0) {
            return Future::complete(null);
        }

        $deferred = new Deferred;
        $this->poll->listen();

        $onWrite = function (Deferred $deferred, $result, $req): void {
            if ($this->queue->isEmpty()) {
                $deferred->error(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->error(new ClosedException("Writing to the file failed due to a closed handle"));
                } else {
                    $deferred->error(new StreamException("Writing to the file failed: " . $error));
                }
            } else {
                $this->position += $result;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }

                $deferred->complete(null);
            }

            $this->poll->done();
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

        return $deferred->getFuture();
    }

    private function trim(int $size): Future
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $onTruncate = function (Deferred $deferred, $result, $req) use ($size): void {
            if ($this->queue->isEmpty()) {
                $deferred->error(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result === -1) {
                $error = \eio_get_last_error($req);
                if ($error === "Bad file descriptor") {
                    $deferred->error(new ClosedException("Truncating the file failed due to a closed handle"));
                } else {
                    $deferred->error(new StreamException("Truncating the file failed: " . $error));
                }
            } else {
                $this->size = $size;
                $this->poll->done();
                $deferred->complete(null);
            }
        };

        \eio_ftruncate(
            $this->fh,
            $size,
            \EIO_PRI_DEFAULT,
            $onTruncate,
            $deferred
        );

        return $deferred->getFuture();
    }
}
