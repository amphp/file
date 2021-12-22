<?php

namespace Amp\File\Driver;

use Amp\Cancellation;
use Amp\File\File;
use Amp\Future;
use function Amp\async;

final class StatusCachingFile implements File
{
    private File $file;

    /** @var callable */
    private $invalidateCallback;

    /**
     * @param File $file Decorated instance.
     * @param callable $invalidateCallback Invalidation callback.
     *
     * @internal
     */
    public function __construct(File $file, callable $invalidateCallback)
    {
        $this->file = $file;
        $this->invalidateCallback = $invalidateCallback;
    }

    public function read(?Cancellation $cancellation = null, int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        return $this->file->read($cancellation, $length);
    }

    public function write(string $bytes): Future
    {
        return async(function () use ($bytes): void {
            try {
                $this->file->write($bytes)->await();
            } finally {
                $this->invalidate();
            }
        });
    }

    public function end(string $data = ""): Future
    {
        return async(function () use ($data): void {
            try {
                $this->file->end($data)->await();
            } finally {
                $this->invalidate();
            }
        });
    }

    public function close(): void
    {
        $this->file->close();
    }

    public function isClosed(): bool
    {
        return $this->file->isClosed();
    }

    public function seek(int $position, int $whence = self::SEEK_SET): int
    {
        return $this->file->seek($position, $whence);
    }

    public function tell(): int
    {
        return $this->file->tell();
    }

    public function atEnd(): bool
    {
        return $this->file->atEnd();
    }

    public function getPath(): string
    {
        return $this->file->getPath();
    }

    public function getMode(): string
    {
        return $this->file->getMode();
    }

    public function truncate(int $size): void
    {
        try {
            $this->file->truncate($size);
        } finally {
            $this->invalidate();
        }
    }

    public function isReadable(): bool
    {
        return $this->file->isReadable();
    }

    public function isSeekable(): bool
    {
        $this->file->isSeekable();
    }

    public function isWritable(): bool
    {
        $this->file->isWritable();
    }

    private function invalidate(): void
    {
        ($this->invalidateCallback)();
    }
}
