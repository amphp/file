<?php

namespace Amp\File\Driver;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\Failure;
use Amp\File\File;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class BlockingFile implements File
{
    private $handle;
    private $path;
    private $mode;

    /**
     * @param resource $handle An open filesystem descriptor.
     * @param string   $path File path.
     * @param string   $mode File open mode.
     */
    public function __construct($handle, string $path, string $mode)
    {
        $this->handle = $handle;
        $this->path = $path;
        $this->mode = $mode;
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            @\fclose($this->handle);
        }
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise
    {
        if ($this->handle === null) {
            return new Failure(new ClosedException("The file '{$this->path}' has been closed"));
        }

        try {
            \set_error_handler(function ($type, $message) {
                throw new StreamException("Failed reading from file '{$this->path}': {$message}");
            });

            $data = \fread($this->handle, $length);
            if ($data === false) {
                throw new StreamException("Failed reading from file '{$this->path}'");
            }

            return new Success($data !== '' ? $data : null);
        } catch (StreamException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function write(string $data): Promise
    {
        if ($this->handle === null) {
            return new Failure(new ClosedException("The file '{$this->path}' has been closed"));
        }

        try {
            \set_error_handler(function ($type, $message) {
                throw new StreamException("Failed writing to file '{$this->path}': {$message}");
            });

            $length = \fwrite($this->handle, $data);
            if ($length === false) {
                throw new StreamException("Failed writing to file '{$this->path}'");
            }

            return new Success;
        } catch (StreamException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function end(string $data = ""): Promise
    {
        return call(function () use ($data) {
            $promise = $this->write($data);

            // ignore any errors
            yield Promise\any([$this->close()]);

            return $promise;
        });
    }

    public function close(): Promise
    {
        if ($this->handle === null) {
            return new Success;
        }

        $handle = $this->handle;
        $this->handle = null;

        try {
            \set_error_handler(function ($type, $message) {
                throw new StreamException("Failed closing file '{$this->path}': {$message}");
            });

            if (\fclose($handle)) {
                return new Success;
            }

            throw new StreamException("Failed closing file '{$this->path}'");
        } catch (StreamException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function truncate(int $size): Promise
    {
        if ($this->handle === null) {
            return new Failure(new ClosedException("The file '{$this->path}' has been closed"));
        }

        try {
            \set_error_handler(function ($type, $message) {
                throw new StreamException("Could not truncate file '{$this->path}': {$message}");
            });

            if (!\ftruncate($this->handle, $size)) {
                throw new StreamException("Could not truncate file '{$this->path}'");
            }

            return new Success;
        } catch (StreamException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function seek(int $position, int $whence = self::SEEK_SET): Promise
    {
        if ($this->handle === null) {
            return new Failure(new ClosedException("The file '{$this->path}' has been closed"));
        }

        switch ($whence) {
            case self::SEEK_SET:
            case self::SEEK_CUR:
            case self::SEEK_END:
                try {
                    \set_error_handler(function ($type, $message) {
                        throw new StreamException("Could not seek in file '{$this->path}': {$message}");
                    });

                    if (\fseek($this->handle, $position, $whence) === -1) {
                        throw new StreamException("Could not seek in file '{$this->path}'");
                    }

                    return new Success($this->tell());
                } catch (StreamException $e) {
                    return new Failure($e);
                } finally {
                    \restore_error_handler();
                }
            default:
                throw new \Error("Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected");
        }
    }

    public function tell(): int
    {
        if ($this->handle === null) {
            throw new ClosedException("The file '{$this->path}' has been closed");
        }

        return \ftell($this->handle);
    }

    public function eof(): bool
    {
        if ($this->handle === null) {
            throw new ClosedException("The file '{$this->path}' has been closed");
        }

        return \feof($this->handle);
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
