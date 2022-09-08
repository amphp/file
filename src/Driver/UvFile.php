<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\File\File;
use Amp\File\Internal;
use Amp\File\PendingOperationError;
use Amp\Future;
use Revolt\EventLoop\Driver\UvDriver as UvLoopDriver;
use function Amp\async;

final class UvFile implements File
{
    private readonly Internal\UvPoll $poll;

    /** @var \UVLoop|resource */
    private $eventLoopHandle;

    /** @var resource */
    private $fh;

    private string $path;

    private string $mode;

    private int $size;

    private int $position;

    private \SplQueue $queue;

    private bool $isActive = false;

    private bool $writable = true;

    private ?Future $closing = null;

    /** @var bool True if ext-uv version is < 0.3.0. */
    private bool $priorVersion;

    private readonly DeferredFuture $onClose;

    /**
     * @param Internal\UvPoll $poll Poll for keeping the loop active.
     * @param resource $fh File handle.
     */
    public function __construct(
        UvLoopDriver $driver,
        Internal\UvPoll $poll,
        $fh,
        string $path,
        string $mode,
        int $size
    ) {
        $this->poll = $poll;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;

        /** @psalm-suppress PropertyTypeCoercion */
        $this->eventLoopHandle = $driver->getHandle();
        $this->position = ($mode[0] === "a") ? $size : 0;

        $this->queue = new \SplQueue;
        $this->onClose = new DeferredFuture;

        $this->priorVersion = \version_compare(\phpversion('uv'), '0.3.0', '<');
    }

    public function __destruct()
    {
        async($this->close(...));
    }

    public function read(?Cancellation $cancellation = null, int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $deferred = new DeferredFuture;
        $this->poll->listen();

        $this->isActive = true;

        $onRead = function ($result, $buffer) use ($deferred): void {
            $this->isActive = false;

            if ($deferred->isComplete()) {
                return;
            }

            if (\is_int($buffer)) {
                $error = \uv_strerror($buffer);
                if ($error === "bad file descriptor") {
                    $deferred->error(new ClosedException("Reading from the file failed due to a closed handle"));
                } else {
                    $deferred->error(new StreamException("Reading from the file failed: " . $error));
                }
                return;
            }

            $length = \strlen($buffer);
            $this->position += $length;
            $deferred->complete($length ? $buffer : null);
        };

        if ($this->priorVersion) {
            $onRead = static function ($fh, $result, $buffer) use ($onRead): void {
                if ($result < 0) {
                    $buffer = $result; // php-uv v0.3.0 changed the callback to put an int in $buffer on error.
                }

                $onRead($result, $buffer);
            };
        }

        \uv_fs_read($this->eventLoopHandle, $this->fh, $this->position, $length, $onRead);

        $id = $cancellation?->subscribe(function (\Throwable $exception) use ($deferred): void {
            $this->isActive = false;
            $deferred->error($exception);
        });

        try {
            return $deferred->getFuture()->await();
        } finally {
            /** @psalm-suppress PossiblyNullArgument $id is non-null if $cancellation is non-null */
            $cancellation?->unsubscribe($id);
            $this->poll->done();
        }
    }

    public function write(string $bytes): void
    {
        if ($this->isActive && $this->queue->isEmpty()) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
        }

        $this->isActive = true;

        if ($this->queue->isEmpty()) {
            $future = $this->push($bytes);
        } else {
            $future = $this->queue->top();
            $future = async(function () use ($future, $bytes): void {
                $future->await();
                $this->push($bytes)->await();
            });
        }

        $this->queue->push($future);

        $future->await();
    }

    public function end(): void
    {
        $this->writable = false;
        $this->close();
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
            $future = async(function () use ($future, $size): void {
                $future->await();
                $this->trim($size)->await();
            });
        }

        $this->queue->push($future);

        $future->await();
    }

    public function seek(int $position, int $whence = \SEEK_SET): int
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        switch ($whence) {
            case self::SEEK_SET:
                $this->position = $position;
                break;
            case self::SEEK_CUR:
                $this->position += $position;
                break;
            case self::SEEK_END:
                $this->position = $this->size + $position;
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

    public function eof(): bool
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

    public function close(): void
    {
        if ($this->closing) {
            $this->closing->await();
            return;
        }

        $deferred = $this->onClose;
        $this->closing = $deferred->getFuture();
        $this->poll->listen();

        \uv_fs_close($this->eventLoopHandle, $this->fh, static function () use ($deferred): void {
            // Ignore errors when closing file, as the handle will become invalid anyway.
            $deferred->complete();
        });

        try {
            $this->closing->await();
        } finally {
            $this->poll->done();
        }
    }

    public function isClosed(): bool
    {
        return $this->closing !== null;
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    private function push(string $data): Future
    {
        $length = \strlen($data);

        if ($length === 0) {
            return Future::complete(null);
        }

        $deferred = new DeferredFuture;
        $this->poll->listen();

        $onWrite = function ($fh, $result) use ($deferred, $length): void {
            if ($this->queue->isEmpty()) {
                $deferred->error(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result < 0) {
                $error = \uv_strerror($result);
                if ($error === "bad file descriptor") {
                    $deferred->error(new ClosedException("Writing to the file failed due to a closed handle"));
                } else {
                    $deferred->error(new StreamException("Writing to the file failed: " . $error));
                }
            } else {
                $this->position += $length;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }
                $deferred->complete(null);
                $this->poll->done();
            }
        };

        \uv_fs_write($this->eventLoopHandle, $this->fh, $data, $this->position, $onWrite);

        return $deferred->getFuture();
    }

    private function trim(int $size): Future
    {
        $deferred = new DeferredFuture;
        $this->poll->listen();

        $onTruncate = function ($fh) use ($deferred, $size): void {
            if ($this->queue->isEmpty()) {
                $deferred->error(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            $this->size = $size;
            $deferred->complete(null);
            $this->poll->done();
        };

        \uv_fs_ftruncate(
            $this->eventLoopHandle,
            $this->fh,
            $size,
            $onTruncate
        );

        return $deferred->getFuture();
    }

    public function isReadable(): bool
    {
        return !$this->isClosed();
    }

    public function isSeekable(): bool
    {
        return !$this->isClosed();
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }
}
