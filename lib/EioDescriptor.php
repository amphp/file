<?php

namespace Amp\Fs;

use Amp\Reactor;
use Amp\Promise;
use Amp\Deferred;

class EioDescriptor implements Descriptor {
    private $reactor;
    private $fh;
    private $increment;
    private $decrement;
    private $isCloseInitialized = false;

    /**
     * @param \Amp\Reactor $reactor
     * @param resource $fh An eio file handle
     * @param callable $inc
     * @param callable $dec
     */
    public function __construct(Reactor $reactor, $fh, callable $inc, callable $dec) {
        $this->reactor = $reactor;
        $this->fh = $fh;
        $this->increment = $inc;
        $this->decrement = $dec;
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
        \call_user_func($this->increment);
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_read($this->fh, $offset, $len, $priority, [$this, "onRead"], $promisor);

        return $promisor->promise();
    }

    private function onRead($promisor, $result, $req) {
        \call_user_func($this->decrement);
        if ($result === -1) {
            $promisor->fail(new \RuntimeException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($offset, $data) {
        \call_user_func($this->increment);
        $promisor = new Deferred;
        $length = 0;
        $priority = \EIO_PRI_DEFAULT;
        \eio_write($this->fh, $data, $length, $offset, $priority, [$this, "onWrite"], $promisor);

        return $promisor->promise();
    }

    private function onWrite($promisor, $result, $req) {
        \call_user_func($this->decrement);
        if ($result === -1) {
            $promisor->fail(new \RuntimeException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($length = 0) {
        \call_user_func($this->increment);
        $promisor = new Deferred;
        $length = 0;
        $priority = \EIO_PRI_DEFAULT;
        \eio_truncate($this->fh, $length, $priority, [$this, "onTruncate"], $promisor);

        return $promisor->promise();
    }

    private function onTruncate($promisor, $result, $req) {
        \call_user_func($this->decrement);
        if ($result === -1) {
            $promisor->fail(new \RuntimeException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat() {
        \call_user_func($this->increment);
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_fstat($this->fh, $priority, [$this, "onStat"], $promisor);

        return $promisor->promise();
    }

    private function onStat($promisor, $result, $req) {
        if ($result === -1) {
            $promisor->fail(new \RuntimeException(
                \eio_get_last_error($req)
            ));
        } else {
            $stat["isdir"] = (bool) ($stat["mode"] & Filesystem::S_IFDIR);
            $stat["isfile"] = (bool) ($stat["mode"] & Filesystem::S_IFREG);
        }
        \call_user_func($this->decrement);
        $promisor->succeed($result);
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->isCloseInitialized = true;
        \call_user_func($this->increment);
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_close($this->fh, $priority, [$this, "onClose"], $promisor);

        return $promisor->promise();
    }

    private function onClose($promisor, $result, $req) {
        \call_user_func($this->decrement);
        if ($result === -1) {
            $promisor->fail(new \RuntimeException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed();
        }
    }
}
