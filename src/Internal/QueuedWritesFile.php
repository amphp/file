<?php

namespace Amp\File\Internal;

use Amp\ByteStream\ClosedException;
use Amp\File\File;
use Amp\File\PendingOperationError;
use Amp\Future;
use function Amp\async;

/** @internal */
abstract class QueuedWritesFile implements File
{
    /** @var \SplQueue<Future<null>> */
    protected readonly \SplQueue $queue;

    protected int $position;

    protected bool $isReading = false;

    private bool $writable;

    public function __construct(
        private readonly string $path,
        private readonly string $mode,
        protected int $size,
    ) {
        if (!\strlen($mode)) {
            throw new \ValueError('File mode cannot be empty');
        }

        $this->queue = new \SplQueue();
        $this->writable = $this->mode[0] !== 'r';
        $this->position = $this->mode[0] === 'a' ? $this->size : 0;
    }

    public function __destruct()
    {
        async($this->close(...));
    }

    /**
     * @return Future<null>
     */
    abstract protected function push(string $data, int $position): Future;

    public function write(string $bytes): void
    {
        if ($this->isReading) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
        }

        if ($this->queue->isEmpty()) {
            $future = $this->push($bytes, $this->position);
        } else {
            $position = $this->position;
            /** @var Future $future */
            $future = $this->queue->top()->map(fn () => $this->push($bytes, $position)->await());
        }

        $this->queue->push($future);

        $future->await();
    }

    public function end(): void
    {
        $this->writable = false;

        if ($this->queue->isEmpty()) {
            $this->close();
            return;
        }

        $future = $this->queue->top()->finally($this->close(...));
        $this->queue->push($future);

        $future->await();
    }

    /**
     * @return Future<null>
     */
    abstract protected function trim(int $size): Future;

    public function truncate(int $size): void
    {
        if ($this->isReading) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            throw new ClosedException("The file is no longer writable");
        }

        if ($this->queue->isEmpty()) {
            $future = $this->trim($size);
        } else {
            $future = $this->queue->top()->map(fn () => $this->trim($size)->await());
        }

        $this->queue->push($future);

        $future->await();
    }

    public function seek(int $position, int $whence = \SEEK_SET): int
    {
        if ($this->isReading) {
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}
