<?php

namespace Amp\Fs;

use Amp\Promise;
use Amp\Deferred;
use Amp\UvReactor;

class UvDescriptor implements Descriptor {
    private $reactor;
    private $fh;
    private $loop;
    private $isCloseInitialized = false;

    /**
     * @param \Amp\UvReactor $reactor
     * @param resource $fh An open uv filesystem descriptor
     */
    public function __construct(UvReactor $reactor, $fh) {
        $this->reactor = $reactor;
        $this->fh = $fh;
        $this->loop = $reactor->getUnderlyingLoop();
    }

    public function __destruct() {
        if (empty($this->isCloseInitialized)) {
            $this->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($offset, $len) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_read($this->loop, $this->fh, $offset, $len, function($fh, $result, $buf) use ($promisor) {
            $this->reactor->delRef();
            if ($result < 0) {
                $promisor->fail(new \RuntimeException(
                    "Failed reading from file handle: " . \uv_strerror($result)
                ));
            } else {
                $promisor->succeed($buf);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function write($offset, $data) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_write($this->loop, $this->fh, $data, $offset, function($fh, $result) use ($promisor) {
            $this->reactor->delRef();
            if ($result < 0) {
                $promisor->fail(new \RuntimeException(
                    "Failed writing to file handle: " . \uv_strerror($result)
                ));
            } else {
                $promisor->succeed($result);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($length = 0) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_ftruncate($this->loop, $this->fh, $length, function($fh) use ($promisor) {
            $this->reactor->delRef();
            if (empty($fh)) {
                $promisor->fail(new \RuntimeException(
                    "Failed truncating file handle"
                ));
            } else {
                $promisor->succeed();
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function stat() {
        // @TODO Pull result from stat cache if it exists
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_fstat($this->loop, $this->fh, function($fh, $stat) use ($promisor) {
            if ($fh) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = empty($stat["isdir"]);
            } else {
                $stat = null;
            }
            $this->reactor->delRef();
            $promisor->succeed($stat);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->isCloseInitialized = true;
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_close($this->loop, $this->fh, function($fh) use ($promisor) {
            $this->reactor->delRef();
            if (empty($fh)) {
                $promisor->fail(new \RuntimeException(
                    "Failed closing file handle"
                ));
            } else {
                $promisor->succeed();
            }
        });

        return $promisor->promise();
    }
}
