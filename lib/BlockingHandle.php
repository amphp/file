<?php

namespace Amp\File;

use Amp\Promise;
use Amp\Success;
use Amp\Failure;

class BlockingHandle implements Handle {
    private $fh;
    private $path;
    private $mode;

    /**
     * @param resource $fh An open uv filesystem descriptor
     */
    public function __construct($fh, $path, $mode) {
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     */
    public function read($len) {
        $data = \fread($this->fh, $len);
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
    public function write($data) {
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
    public function close() {
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
    public function seek($position, $whence = \SEEK_SET) {
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
    public function tell() {
        return \ftell($this->fh);
    }

    /**
     * {@inheritdoc}
     */
    public function eof() {
        return \feof($this->fh);
    }

    /**
     * {@inheritdoc}
     */
    public function path() {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function mode() {
        return $this->mode;
    }
}
