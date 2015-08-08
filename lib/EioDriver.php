<?php

namespace Amp\File;

use Amp\Promise;
use Amp\Success;
use Amp\Failure;
use Amp\Deferred;

class EioDriver implements Driver {
    private static $isEioInitialized = false;
    private $stream;
    private $watcher;
    private $callableDelReq;
    private $internalIncrement;
    private $internalDecrement;
    private $pending = 0;

    public function __construct() {
        if (empty(self::$isEioInitialized)) {
            \eio_init();
            self::$isEioInitialized = true;
        }
        $this->stream = \eio_get_event_stream();
        $this->callableDelReq = function() {
            $this->decrementPending();
        };
        $this->internalIncrement = function() {
            $this->incrementPending();
        };
        $this->internalDecrement = function() {
            $this->decrementPending();
        };
        $this->watcher = \Amp\onReadable($this->stream, function() {
            while (\eio_npending()) {
                \eio_poll();
            }
        }, $options = ["enable" => false]);
    }

    private function incrementPending() {
        if ($this->pending++ === 0) {
            \Amp\enable($this->watcher);
        }
    }

    private function decrementPending() {
        if ($this->pending-- === 1) {
            \Amp\disable($this->watcher);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path) {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$promisor, $path];
        \eio_stat($path, $priority, [$this, "onStat"], $data);

        return $promisor->promise();
    }

    private function onStat($data, $result, $req) {
        list($promisor, $path) = $data;
        $this->decrementPending();
        if ($result === -1) {
            $promisor->succeed(null);
        } else {
            StatCache::set($path, $result);
            $promisor->succeed($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            $promisor->succeed((bool) $result);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function isdir($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if ($result) {
                $promisor->succeed(!($result["mode"] & \EIO_S_IFREG));
            } else {
                $promisor->succeed(false);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function isfile($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if ($result) {
                $promisor->succeed((bool) ($result["mode"] & \EIO_S_IFREG));
            } else {
                $promisor->succeed(false);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function size($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if (empty($result)) {
                $promisor->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            } elseif (($result["mode"] & \EIO_S_IFREG)) {
                $promisor->succeed($result["size"]);
            } else {
                $promisor->fail(new FilesystemException(
                    "Specified path is not a regular file"
                ));
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function mtime($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if ($result) {
                $promisor->succeed($result["mtime"]);
            } else {
                $promisor->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function atime($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if ($result) {
                $promisor->succeed($result["atime"]);
            } else {
                $promisor->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function ctime($path) {
        $promisor = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($promisor) {
            if ($result) {
                $promisor->succeed($result["ctime"]);
            } else {
                $promisor->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function lstat($path) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_lstat($path, $priority, [$this, "onLstat"], $promisor);

        return $promisor->promise();
    }

    private function onLstat($promisor, $result, $req) {
        $this->decrementPending();
        if ($result === -1) {
            $promisor->succeed(null);
        } else {
            $promisor->succeed($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($target, $link) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_symlink($target, $link, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    private function onGenericResult($promisor, $result, $req) {
        $this->decrementPending();
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($from, $to) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_rename($from, $to, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$promisor, $path];
        \eio_unlink($path, $priority, [$this, "onUnlink"], $data);

        return $promisor->promise();
    }

    private function onUnlink($data, $result, $req) {
        list($promisor, $path) = $data;
        $this->decrementPending();
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::clear($path);
            $promisor->succeed(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0644) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_mkdir($path, $mode, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$promisor, $path];
        \eio_rmdir($path, $priority, [$this, "onRmdir"], $data);

        return $promisor->promise();
    }

    private function onRmdir($data, $result, $req) {
        list($promisor, $path) = $data;
        $this->decrementPending();
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::clear($path);
            $promisor->succeed(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scandir($path) {
        $this->incrementPending();
        $promisor = new Deferred;
        $flags = \EIO_READDIR_STAT_ORDER | \EIO_READDIR_DIRS_FIRST;
        $priority = \EIO_PRI_DEFAULT;
        \eio_readdir($path, $flags, $priority, [$this, "onScandir"], $promisor);

        return $promisor->promise();
    }

    private function onScandir($promisor, $result, $req) {
        $this->decrementPending();
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed($result["names"]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_chmod($path, $mode, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid, $gid) {
        $this->incrementPending();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_chown($path, $uid, $gid, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function touch($path) {
        $atime = $mtime = time();
        $promisor = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_utime($path, $atime, $mtime, $priority, [$this, "onGenericResult"], $promisor);

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function get($path) {
        $flags = $flags = \EIO_O_RDONLY;
        $mode = 0;
        $priority = \EIO_PRI_DEFAULT;

        $this->incrementPending();
        $promisor = new Deferred;
        \eio_open($path, $flags, $mode, $priority, [$this, "onGetOpen"], $promisor);

        return $promisor->promise();
    }

    private function onGetOpen($promisor, $result, $req) {
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $priority = \EIO_PRI_DEFAULT;
            \eio_fstat($result, $priority, [$this, "onGetFstat"], [$result, $promisor]);
        }
    }

    private function onGetFstat($fhAndPromisor, $result, $req) {
        list($fh, $promisor) = $fhAndPromisor;
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
            return;
        }

        $offset = 0;
        $length = $result["size"];
        $priority = \EIO_PRI_DEFAULT;
        \eio_read($fh, $length, $offset, $priority, [$this, "onGetRead"], $fhAndPromisor);
    }

    private function onGetRead($fhAndPromisor, $result, $req) {
        list($fh, $promisor) = $fhAndPromisor;
        $priority = \EIO_PRI_DEFAULT;
        \eio_close($fh, $priority, $this->callableDelReq);
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents) {
        $flags = \EIO_O_RDWR | \EIO_O_CREAT;
        $mode = \EIO_S_IRUSR | \EIO_S_IWUSR | \EIO_S_IXUSR;
        $priority = \EIO_PRI_DEFAULT;

        $this->incrementPending();
        $promisor = new Deferred;
        $data = [$contents, $promisor];
        \eio_open($path, $flags, $mode, $priority, [$this, "onPutOpen"], $data);

        return $promisor->promise();
    }

    private function onPutOpen($data, $result, $req) {
        list($contents, $promisor) = $data;
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $length = strlen($contents);
            $offset = 0;
            $priority = \EIO_PRI_DEFAULT;
            $callback = [$this, "onPutWrite"];
            $fhAndPromisor = [$result, $promisor];
            \eio_write($result, $contents, $length, $offset, $priority, $callback, $fhAndPromisor);
        }
    }

    private function onPutWrite($fhAndPromisor, $result, $req) {
        list($fh, $promisor) = $fhAndPromisor;
        \eio_close($fh);
        $priority = \EIO_PRI_DEFAULT;
        \eio_close($fh, $priority, $this->callableDelReq);
        if ($result === -1) {
            $promisor->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $promisor->succeed($result);
        }
    }

    public function __destruct() {
        $this->stream = null;
        \Amp\cancel($this->watcher);

        /**
         * pecl/eio has a race condition issue when freeing threaded
         * resources and we can get intermittent segfaults at script
         * shutdown in certain cases if we don't wait for a moment.
         *
         * @TODO see if we can PR a fix for this problem in pecl/eio
         */
        usleep(1000);
    }
}
