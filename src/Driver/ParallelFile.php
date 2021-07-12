<?php

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Failure;
use Amp\File\File;
use Amp\File\Internal;
use Amp\File\PendingOperationError;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class ParallelFile implements File
{
    /** @var Worker */
    private $worker;

    /** @var int|null */
    private $id;

    /** @var string */
    private $path;

    /** @var int */
    private $position;

    /** @var int */
    private $size;

    /** @var string */
    private $mode;

    /** @var bool True if an operation is pending. */
    private $busy = false;

    /** @var int Number of pending write operations. */
    private $pendingWrites = 0;

    /** @var bool */
    private $writable = true;

    /** @var Promise|null */
    private $closing;

    /**
     * @param Worker $worker
     * @param int    $id
     * @param string $path
     * @param int    $size
     * @param string $mode
     */
    public function __construct(Worker $worker, int $id, string $path, int $size, string $mode)
    {
        $this->worker = $worker;
        $this->id = $id;
        $this->path = $path;
        $this->size = $size;
        $this->mode = $mode;
        $this->position = $this->mode[0] === 'a' ? $this->size : 0;
    }

    public function __destruct()
    {
        if ($this->id !== null) {
            $this->close();
        }
    }

    public function close(): Promise
    {
        if ($this->closing) {
            return $this->closing;
        }

        $this->writable = false;

        if ($this->worker->isRunning()) {
            $this->closing = $this->worker->enqueue(new Internal\FileTask('fclose', [], $this->id));
            $this->id = null;
        } else {
            $this->closing = new Success;
        }

        return $this->closing;
    }

    public function truncate(int $size): Promise
    {
        if ($this->id === null) {
            return new Failure(new ClosedException("The file has been closed"));
        }

        if ($this->busy) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            return new Failure(new ClosedException("The file is no longer writable"));
        }

        return call(function () use ($size) {
            ++$this->pendingWrites;
            $this->busy = true;

            try {
                yield $this->worker->enqueue(new Internal\FileTask('ftruncate', [$size], $this->id));
            } catch (TaskException $exception) {
                throw new StreamException("Reading from the file failed", 0, $exception);
            } catch (WorkerException $exception) {
                throw new StreamException("Sending the task to the worker failed", 0, $exception);
            } finally {
                if (--$this->pendingWrites === 0) {
                    $this->busy = false;
                }
            }
        });
    }

    public function eof(): bool
    {
        return $this->pendingWrites === 0 && $this->size <= $this->position;
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise
    {
        if ($this->id === null) {
            return new Failure(new ClosedException("The file has been closed"));
        }

        if ($this->busy) {
            throw new PendingOperationError;
        }

        return call(function () use ($length) {
            $this->busy = true;

            try {
                $data = yield $this->worker->enqueue(new Internal\FileTask('fread', [$length], $this->id));

                if ($data !== null) {
                    $this->position += \strlen($data);
                }
            } catch (TaskException $exception) {
                throw new StreamException("Reading from the file failed", 0, $exception);
            } catch (WorkerException $exception) {
                throw new StreamException("Sending the task to the worker failed", 0, $exception);
            } finally {
                $this->busy = false;
            }

            return $data;
        });
    }

    public function write(string $data): Promise
    {
        if ($this->id === null) {
            return new Failure(new ClosedException("The file has been closed"));
        }

        if ($this->busy && $this->pendingWrites === 0) {
            throw new PendingOperationError;
        }

        if (!$this->writable) {
            return new Failure(new ClosedException("The file is no longer writable"));
        }

        return call(function () use ($data) {
            ++$this->pendingWrites;
            $this->busy = true;

            try {
                yield $this->worker->enqueue(new Internal\FileTask('fwrite', [$data], $this->id));
                $this->position += \strlen($data);
            } catch (TaskException $exception) {
                throw new StreamException("Writing to the file failed", 0, $exception);
            } catch (WorkerException $exception) {
                throw new StreamException("Sending the task to the worker failed", 0, $exception);
            } finally {
                if (--$this->pendingWrites === 0) {
                    $this->busy = false;
                }
            }
        });
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

    public function seek(int $offset, int $whence = SEEK_SET): Promise
    {
        if ($this->id === null) {
            return new Failure(new ClosedException("The file has been closed"));
        }

        if ($this->busy) {
            throw new PendingOperationError;
        }

        return call(function () use ($offset, $whence) {
            switch ($whence) {
                case self::SEEK_SET:
                case self::SEEK_CUR:
                case self::SEEK_END:
                    try {
                        $this->position = yield $this->worker->enqueue(
                            new Internal\FileTask('fseek', [$offset, $whence], $this->id)
                        );

                        if ($this->position > $this->size) {
                            $this->size = $this->position;
                        }

                        return $this->position;
                    } catch (TaskException $exception) {
                        throw new StreamException('Seeking in the file failed.', 0, $exception);
                    } catch (WorkerException $exception) {
                        throw new StreamException("Sending the task to the worker failed", 0, $exception);
                    }

                default:
                    throw new \Error('Invalid whence value. Use SEEK_SET, SEEK_CUR, or SEEK_END.');
            }
        });
    }

    public function tell(): int
    {
        return $this->position;
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
