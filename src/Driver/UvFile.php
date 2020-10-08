<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Deferred;
use Amp\File\File;
use Amp\File\Internal;
use Amp\File\PendingOperationError;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use function Amp\async;
use function Amp\await;

final class UvFile implements File
{
    private Internal\UvPoll $poll;

    /** @var \UVLoop|resource */
    private $loop;

    /** @var resource */
    private $fh;

    private string $path;

    private string $mode;

    private int $size;

    private int $position;

    private \SplQueue $queue;

    private bool $isActive = false;

    private bool $writable = true;

    private ?Promise $closing = null;

    /** @var bool True if ext-uv version is < 0.3.0. */
    private bool $priorVersion;

    /**
     * @param \Amp\Loop\UvDriver $driver
     * @param Internal\UvPoll    $poll Poll for keeping the loop active.
     * @param resource           $fh File handle.
     * @param string             $path
     * @param string             $mode
     * @param int                $size
     */
    public function __construct(
        Loop\UvDriver $driver,
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
        $this->loop = $driver->getHandle();
        $this->position = ($mode[0] === "a") ? $size : 0;

        $this->queue = new \SplQueue;

        $this->priorVersion = \version_compare(\phpversion('uv'), '0.3.0', '<');
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $this->isActive = true;

        $onRead = function ($result, $buffer) use ($deferred): void {
            $this->isActive = false;

            if (\is_int($buffer)) {
                $error = \uv_strerror($buffer);
                if ($error === "bad file descriptor") {
                    $deferred->fail(new ClosedException("Reading from the file failed due to a closed handle"));
                } else {
                    $deferred->fail(new StreamException("Reading from the file failed: " . $error));
                }
                return;
            }

            $length = \strlen($buffer);
            $this->position += $length;
            $deferred->resolve($length ? $buffer : null);
        };

        if ($this->priorVersion) {
            $onRead = static function ($fh, $result, $buffer) use ($onRead): void {
                if ($result < 0) {
                    $buffer = $result; // php-uv v0.3.0 changed the callback to put an int in $buffer on error.
                }

                $onRead($result, $buffer);
            };
        }

        \uv_fs_read($this->loop, $this->fh, $this->position, $length, $onRead);

        return await($deferred->promise());
    }

    public function write(string $data): void
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
            $promise = async(function () use ($promise, $data): void {
                await($promise);
                await($this->push($data));
            });
        }

        $this->queue->push($promise);

        await($promise);
    }

    public function end(string $data = ""): void
    {
        try {
            $this->write($data);
            $this->writable = false;
        } finally {
            $this->close();
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
            $promise = $this->trim($size);
        } else {
            $promise = $this->queue->top();
            $promise = async(function () use ($promise, $size): void {
                await($promise);
                await($this->trim($size));
            });
        }

        $this->queue->push($promise);

        await($promise);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): int
    {
        if ($this->isActive) {
            throw new PendingOperationError;
        }

        $offset = (int) $offset;
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

    public function close(): void
    {
        if ($this->closing) {
            await($this->closing);
            return;
        }

        $deferred = new Deferred;
        $this->poll->listen($this->closing = $deferred->promise());

        \uv_fs_close($this->loop, $this->fh, static function () use ($deferred): void {
            // Ignore errors when closing file, as the handle will become invalid anyway.
            $deferred->resolve();
        });

        await($deferred->promise());
    }

    private function push(string $data): Promise
    {
        $length = \strlen($data);

        if ($length === 0) {
            return new Success(0);
        }

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onWrite = function ($fh, $result) use ($deferred, $length): void {
            if ($this->queue->isEmpty()) {
                $deferred->fail(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            if ($result < 0) {
                $error = \uv_strerror($result);
                if ($error === "bad file descriptor") {
                    $deferred->fail(new ClosedException("Writing to the file failed due to a closed handle"));
                } else {
                    $deferred->fail(new StreamException("Writing to the file failed: " . $error));
                }
            } else {
                $this->position += $length;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }
                $deferred->resolve($length);
            }
        };

        \uv_fs_write($this->loop, $this->fh, $data, $this->position, $onWrite);

        return $deferred->promise();
    }

    private function trim(int $size): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $onTruncate = function ($fh) use ($deferred, $size): void {
            if ($this->queue->isEmpty()) {
                $deferred->fail(new ClosedException('No pending write, the file may have been closed'));
            }

            $this->queue->shift();
            if ($this->queue->isEmpty()) {
                $this->isActive = false;
            }

            $this->size = $size;
            $deferred->resolve();
        };

        \uv_fs_ftruncate(
            $this->loop,
            $this->fh,
            $size,
            $onTruncate
        );

        return $deferred->promise();
    }
}
