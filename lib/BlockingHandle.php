<?php declare(strict_types = 1);

namespace Amp\File;

use Amp\{ Success, Failure };
use Interop\Async\Awaitable;

class BlockingHandle implements Handle {
    private $fh;
    private $path;
    private $mode;

    /**
     * @param resource $fh An open uv filesystem descriptor
     */
    public function __construct($fh, string $path, string $mode) {
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length): Awaitable {
        $data = \fread($this->fh, $length);
        if ($data !== false) {
            return new Success($data);
        } else {
            return new Failure(new FilesystemException(
                "Failed reading from file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): Awaitable {
        $len = \fwrite($this->fh, $data);
        if ($len !== false) {
            return new Success($data);
        } else {
            return new Failure(new FilesystemException(
                "Failed writing to file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Awaitable {
        if (\fclose($this->fh)) {
            return new Success;
        } else {
            return new Failure(new FilesystemException(
                "Failed closing file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $position, int $whence = \SEEK_SET) {
        switch ($whence) {
            case \SEEK_SET:
            case \SEEK_CUR:
            case \SEEK_END:
                \fseek($this->fh, $position, $whence);
                return;
            default:
                throw new FilesystemException(
                    "Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected"
                );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int {
        return \ftell($this->fh);
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool {
        return \feof($this->fh);
    }

    /**
     * {@inheritdoc}
     */
    public function path(): string {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function mode(): string {
        return $this->mode;
    }
}
