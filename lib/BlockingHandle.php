<?php

namespace Amp\File;

use Amp\{ Success, Failure };
use Interop\Async\Promise;

class BlockingHandle implements Handle {
    private $fh;
    private $path;
    private $mode;

    /**
     * @param resource $fh An open uv filesystem descriptor
     * @param string $path
     * @param string $mode
     */
    public function __construct($fh, string $path, string $mode) {
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
    }
    
    public function __destruct() {
        if ($this->fh !== null) {
            \fclose($this->fh);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function read(int $length): Promise {
        if ($this->fh === null) {
            throw new \Error("The file has been closed");
        }
        
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
    public function write(string $data): Promise {
        if ($this->fh === null) {
            throw new \Error("The file has been closed");
        }
        
        $len = \fwrite($this->fh, $data);
        if ($len !== false) {
            return new Success($len);
        } else {
            return new Failure(new FilesystemException(
                "Failed writing to file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Promise {
        if ($this->fh === null) {
            throw new \Error("The file has already been closed");
        }
        
        $fh = $this->fh;
        $this->fh = null;
        
        if (\fclose($fh)) {
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
    public function seek(int $position, int $whence = \SEEK_SET): Promise {
        if ($this->fh === null) {
            throw new \Error("The file has been closed");
        }
        
        switch ($whence) {
            case \SEEK_SET:
            case \SEEK_CUR:
            case \SEEK_END:
                if (@\fseek($this->fh, $position, $whence) === -1) {
                    return new Failure(new FilesystemException("Could not seek in file"));
                }
                return new Success($this->tell());
            default:
                throw new \Error(
                    "Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected"
                );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int {
        if ($this->fh === null) {
            throw new \Error("The file has been closed");
        }
        
        return \ftell($this->fh);
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool {
        if ($this->fh === null) {
            throw new \Error("The file has been closed");
        }
        
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
