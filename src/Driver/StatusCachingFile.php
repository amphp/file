<?php declare(strict_types=1);

namespace Amp\File\Driver;

use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\File\File;
use Amp\File\LockType;
use Amp\File\Whence;

/**
 * @implements \IteratorAggregate<int, string>
 */
final class StatusCachingFile implements File, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    private readonly File $file;

    private readonly \Closure $invalidateCallback;

    /**
     * @param File $file Decorated instance.
     * @param \Closure $invalidateCallback Invalidation callback.
     *
     * @internal
     */
    public function __construct(File $file, \Closure $invalidateCallback)
    {
        $this->file = $file;
        $this->invalidateCallback = $invalidateCallback;
    }

    #[\Override]
    public function read(?Cancellation $cancellation = null, int $length = self::DEFAULT_READ_LENGTH): ?string
    {
        return $this->file->read($cancellation, $length);
    }

    #[\Override]
    public function write(string $bytes): void
    {
        try {
            $this->file->write($bytes);
        } finally {
            $this->invalidate();
        }
    }

    #[\Override]
    public function end(): void
    {
        try {
            $this->file->end();
        } finally {
            $this->invalidate();
        }
    }

    #[\Override]
    public function lock(LockType $type, ?Cancellation $cancellation = null): void
    {
        $this->file->lock($type, $cancellation);
    }

    #[\Override]
    public function tryLock(LockType $type): bool
    {
        return $this->file->tryLock($type);
    }

    #[\Override]
    public function unlock(): void
    {
        $this->file->unlock();
    }

    #[\Override]
    public function getLockType(): ?LockType
    {
        return $this->file->getLockType();
    }

    #[\Override]
    public function close(): void
    {
        $this->file->close();
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->file->isClosed();
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->file->onClose($onClose);
    }

    #[\Override]
    public function seek(int $position, Whence $whence = Whence::Start): int
    {
        return $this->file->seek($position, $whence);
    }

    #[\Override]
    public function tell(): int
    {
        return $this->file->tell();
    }

    #[\Override]
    public function eof(): bool
    {
        return $this->file->eof();
    }

    #[\Override]
    public function getPath(): string
    {
        return $this->file->getPath();
    }

    #[\Override]
    public function getMode(): string
    {
        return $this->file->getMode();
    }

    #[\Override]
    public function truncate(int $size): void
    {
        try {
            $this->file->truncate($size);
        } finally {
            $this->invalidate();
        }
    }

    #[\Override]
    public function isReadable(): bool
    {
        return $this->file->isReadable();
    }

    #[\Override]
    public function isSeekable(): bool
    {
        return $this->file->isSeekable();
    }

    #[\Override]
    public function isWritable(): bool
    {
        return $this->file->isWritable();
    }

    private function invalidate(): void
    {
        ($this->invalidateCallback)();
    }
}
