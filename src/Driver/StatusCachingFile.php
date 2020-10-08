<?php

namespace Amp\File\Driver;

use Amp\File\File;

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

    public function read(int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        return $this->file->read($length);
    }

    public function write(string $data): void
    {
        try {
            $this->file->write($data);
        } finally {
            $this->invalidate();
        }
    }

    public function end(string $data = ""): void
    {
        try {
            $this->file->end($data);
        } finally {
            $this->invalidate();
        }
    }

    public function close(): void
    {
        $this->file->close();
    }

    public function seek(int $position, int $whence = self::SEEK_SET): int
    {
        return $this->file->seek($position, $whence);
    }

    public function tell(): int
    {
        return $this->file->tell();
    }

    public function eof(): bool
    {
        return $this->file->eof();
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

    private function invalidate(): void
    {
        ($this->invalidateCallback)();
    }
}
