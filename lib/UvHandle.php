<?php

namespace Amp\File;

use Amp\Promise;
use Amp\Deferred;
use Amp\UvReactor;

class UvHandle implements Handle {
    const OP_READ = 1;
    const OP_WRITE = 2;

    private $reactor;
    private $fh;
    private $path;
    private $mode;
    private $size;
    private $loop;
    private $position;
    private $queue = [];
    private $pendingWriteOps = 0;
    private $isActive = false;
    private $isCloseInitialized = false;

    public function __construct(UvReactor $reactor, $fh, $path, $mode, $size) {
        $this->reactor = $reactor;
        $this->fh = $fh;
        $this->path = $path;
        $this->mode = $mode;
        $this->size = $size;
        $this->loop = $reactor->getLoop();
        $this->position = ($mode[0] === "a") ? $size : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function read($readLen) {
        $promisor = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_READ;
        $op->position = $this->position;
        $op->promisor = $promisor;
        $op->readLen = $readLen;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->isActive = true;
            $this->doRead($op);
        }

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function write($writeData) {
        $this->pendingWriteOps++;
        $promisor = new Deferred;
        $op = new \StdClass;
        $op->type = self::OP_WRITE;
        $op->position = $this->position;
        $op->promisor = $promisor;
        $op->writeData = $writeData;
        if ($this->isActive) {
            $this->queue[] = $op;
        } else {
            $this->isActive = true;
            $this->doWrite($op);
        }

        return $promisor->promise();
    }

    private function doRead($op) {
        $this->reactor->addRef();
        $onRead = function ($fh, $result, $buffer) use ($op) {
            $this->isActive = false;
            $this->reactor->delRef();
            if ($result < 0) {
                $op->promisor->fail(new FilesystemException(
                    \uv_strerror($result)
                ));
            } else {
                $this->position = $op->position + strlen($buffer);
                $op->promisor->succeed($buffer);
            }
            if ($this->queue) {
                $this->dequeue();
            }
        };
        \uv_fs_read($this->loop, $this->fh, $op->position, $op->readLen, $onRead);
    }

    private function doWrite($op) {
        $this->reactor->addRef();
        $onWrite = function ($fh, $result) use ($op) {
            $this->isActive = false;
            $this->reactor->delRef();
            if ($result < 0) {
                $op->promisor->fail(new FilesystemException(
                    \uv_strerror($result)
                ));
            } else {
                StatCache::clear($this->path);
                $bytesWritten = \strlen($op->writeData);
                $this->pendingWriteOps--;
                $newPosition = $op->position + $bytesWritten;
                $delta = $newPosition - $this->position;
                $this->position = ($this->mode[0] === "a") ? $this->position : $newPosition;
                $this->size += $delta;
                $op->promisor->succeed($result);
            }
            if ($this->queue) {
                $this->dequeue();
            }
        };
        \uv_fs_write($this->loop, $this->fh, $op->writeData, $op->position, $onWrite);
    }

    private function dequeue() {
        $this->isActive = true;
        $op = \array_shift($this->queue);
        switch ($op->type) {
            case self::OP_READ: $this->doRead($op); break;
            case self::OP_WRITE: $this->doWrite($op); break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = \SEEK_SET) {
        $offset = (int) $offset;
        switch ($whence) {
            case \SEEK_SET:
                $this->position = $offset;
                break;
            case \SEEK_CUR:
                $this->position = $this->position + $offset;
                break;
            case \SEEK_END:
                $this->position = $this->size + $offset;
                break;
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
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof() {
        return ($this->pendingWriteOps > 0) ? false : ($this->size <= $this->position);
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

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->isCloseInitialized = true;
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_close($this->loop, $this->fh, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed();
        });

        return $promisor->promise();
    }

    public function __destruct() {
        if (empty($this->isCloseInitialized)) {
            $this->close();
        }
    }
}
