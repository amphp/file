<?php

namespace Amp\Fs;

use Amp\Promise;
use Amp\Success;
use Amp\Failure;

class BlockingDescriptor implements Descriptor {
    private $fh;

    /**
     * @param resource $fh An open uv filesystem descriptor
     */
    public function __construct($fh, $path) {
        $this->fh = $fh;
        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function read($offset, $len) {
        \fseek($this->fh, $offset);
        $data = \fread($this->fh, $len);

        if ($data !== false) {
            return new Success($data);
        } else {
            return new Failure(new \RuntimeException(
                "Failed reading from file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($offset, $data) {
        \fseek($this->fh, $offset);
        $len = \fwrite($this->fh, $data);

        if ($len !== false) {
            return new Success($data);
        } else {
            return new Failure(new \RuntimeException(
                "Failed writing to file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($length = 0) {
        if (ftruncate($this->fh, $length)) {
            return new Success;
        } else {
            return new Failure(new \RuntimeException(
                "Failed truncating file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat() {
        if ($stat = fstat($this->fh)) {
            $stat["isfile"] = (bool) is_file($this->path);
            $stat["isdir"] = empty($stat["isfile"]);
            return new Success($stat);
        } else {
            return new Failure(new \RuntimeException(
                "File handle stat failed"
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
            return new Failure(new \RuntimeException(
                "Failed closing file handle"
            ));
        }
    }
}
