<?php

namespace Amp\File\Driver;

use Amp\File\File;
use Amp\Promise;

final class StatusCachingFile implements File
{
    /** @var File */
    private $file;

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

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise
    {
        return $this->file->read($length);
    }

    public function write(string $data): Promise
    {
        return $this->invalidate($this->file->write($data));
    }

    public function end(string $data = ""): Promise
    {
        return $this->invalidate($this->file->end($data));
    }

    public function close(): Promise
    {
        return $this->file->close();
    }

    public function seek(int $position, int $whence = self::SEEK_SET): Promise
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

    public function truncate(int $size): Promise
    {
        return $this->invalidate($this->file->truncate($size));
    }

    private function invalidate(Promise $promise): Promise
    {
        $promise->onResolve(function () {
            ($this->invalidateCallback)();
        });

        return $promise;
    }
}
