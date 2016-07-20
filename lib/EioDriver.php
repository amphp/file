<?php

namespace Amp\File;

use Amp\Success;
use Amp\Failure;
use Amp\Deferred;
use Interop\Async\Loop;

class EioDriver implements Driver {
    private $watcher;
    private $pending = 0;
    private $incrementor;
    private $callableDecrementor;
    private static $stream;

    /**
     * We have to keep a static reference of eio event streams,
     * because if we don't, garbage collection can unload eio's
     * underlying pipe via a system close() call before it's
     * finished which generates a SIGPIPE.
     */
    public function __construct() {
        if (empty(self::$stream)) {
            \eio_init();
            self::$stream = \eio_get_event_stream();
        }
        $this->callableDecrementor = function() {
            \call_user_func($this->incrementor, -1);
        };
        $this->incrementor = function ($increment) {
            switch ($increment) {
                case 1:
                case -1:
                    $this->pending += $increment;
                    break;
                default:
                    throw new FilesystemException(
                        "Invalid pending event increment; 1 or -1 required"
                    );
            }
            if ($this->pending === 0) {
                Loop::disable($this->watcher);
            } elseif ($this->pending === 1) {
                Loop::enable($this->watcher);
            }
        };
        $this->watcher = Loop::onReadable(self::$stream, function() {
            while (\eio_npending()) {
                \eio_poll();
            }
        });
        Loop::disable($this->watcher);
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode) {
        switch ($mode) {
            case "r":   $flags = \EIO_O_RDONLY; break;
            case "r+":  $flags = \EIO_O_RDWR; break;
            case "w":   $flags = \EIO_O_WRONLY | \EIO_O_CREAT; break;
            case "w+":  $flags = \EIO_O_RDWR | \EIO_O_CREAT; break;
            case "a":   $flags = \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_APPEND; break;
            case "a+":  $flags = \EIO_O_RDWR | \EIO_O_CREAT | \EIO_O_APPEND; break;
            case "x":   $flags = \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_EXCL; break;
            case "x+":  $flags = \EIO_O_RDWR | \EIO_O_CREAT | \EIO_O_EXCL; break;
            case "c":   $flags = \EIO_O_WRONLY | \EIO_O_CREAT; break;
            case "c+":  $flags = \EIO_O_RDWR | \EIO_O_CREAT; break;
            default: return new Failure(new FilesystemException(
                "Invalid open mode"
            ));
        }
        $chmod = ($flags & \EIO_O_CREAT) ? 0644 : 0;
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $openArr = [$mode, $path, $deferred];
        \eio_open($path, $flags, $chmod, $priority = null, [$this, "onOpenHandle"], $openArr);

        return $deferred->getAwaitable();
    }

    private function onOpenHandle($openArr, $result, $req) {
        list($mode, $path, $deferred) = $openArr;
        if ($result === -1) {
            \call_user_func($this->incrementor, -1);
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } elseif ($mode[0] === "a") {
            \array_unshift($openArr, $result);
            \eio_ftruncate($result, $offset = 0, $priority = null, [$this, "onOpenFtruncate"], $openArr);
        } else {
            \array_unshift($openArr, $result);
            \eio_fstat($result, $priority = null, [$this, "onOpenFstat"], $openArr);
        }
    }

    private function onOpenFtruncate($openArr, $result, $req) {
        \call_user_func($this->incrementor, -1);
        list($fh, $mode, $path, $deferred) = $openArr;
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $handle = new EioHandle($this->incrementor, $fh, $path, $mode, $size = 0);
            $deferred->resolve($handle);
        }
    }

    private function onOpenFstat($openArr, $result, $req) {
        \call_user_func($this->incrementor, -1);
        list($fh, $mode, $path, $deferred) = $openArr;
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::set($path, $result);
            $handle = new EioHandle($this->incrementor, $fh, $path, $mode, $result["size"]);
            $deferred->resolve($handle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path) {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        \eio_stat($path, $priority, [$this, "onStat"], $data);

        return $deferred->getAwaitable();
    }

    private function onStat($data, $result, $req) {
        list($deferred, $path) = $data;
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->resolve(null);
        } else {
            StatCache::set($path, $result);
            $deferred->resolve($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            $deferred->resolve((bool) $result);
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function isdir($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if ($result) {
                $deferred->resolve(!($result["mode"] & \EIO_S_IFREG));
            } else {
                $deferred->resolve(false);
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function isfile($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if ($result) {
                $deferred->resolve((bool) ($result["mode"] & \EIO_S_IFREG));
            } else {
                $deferred->resolve(false);
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function size($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if (empty($result)) {
                $deferred->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            } elseif (($result["mode"] & \EIO_S_IFREG)) {
                $deferred->resolve($result["size"]);
            } else {
                $deferred->fail(new FilesystemException(
                    "Specified path is not a regular file"
                ));
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function mtime($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if ($result) {
                $deferred->resolve($result["mtime"]);
            } else {
                $deferred->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function atime($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if ($result) {
                $deferred->resolve($result["atime"]);
            } else {
                $deferred->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function ctime($path) {
        $deferred = new Deferred;
        $this->stat($path)->when(function ($error, $result) use ($deferred) {
            if ($result) {
                $deferred->resolve($result["ctime"]);
            } else {
                $deferred->fail(new FilesystemException(
                    "Specified path does not exist"
                ));
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function lstat($path) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_lstat($path, $priority, [$this, "onLstat"], $deferred);

        return $deferred->getAwaitable();
    }

    private function onLstat($deferred, $result, $req) {
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->resolve(null);
        } else {
            $deferred->resolve($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($target, $link) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_symlink($target, $link, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    private function onGenericResult($deferred, $result, $req) {
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $deferred->resolve(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($from, $to) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_rename($from, $to, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        \eio_unlink($path, $priority, [$this, "onUnlink"], $data);

        return $deferred->getAwaitable();
    }

    private function onUnlink($data, $result, $req) {
        list($deferred, $path) = $data;
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::clear($path);
            $deferred->resolve(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0644) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_mkdir($path, $mode, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        \eio_rmdir($path, $priority, [$this, "onRmdir"], $data);

        return $deferred->getAwaitable();
    }

    private function onRmdir($data, $result, $req) {
        list($deferred, $path) = $data;
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            StatCache::clear($path);
            $deferred->resolve(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scandir($path) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $flags = \EIO_READDIR_STAT_ORDER | \EIO_READDIR_DIRS_FIRST;
        $priority = \EIO_PRI_DEFAULT;
        \eio_readdir($path, $flags, $priority, [$this, "onScandir"], $deferred);

        return $deferred->getAwaitable();
    }

    private function onScandir($deferred, $result, $req) {
        \call_user_func($this->incrementor, -1);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $deferred->resolve($result["names"]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_chmod($path, $mode, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid, $gid) {
        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_chown($path, $uid, $gid, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function touch($path) {
        $atime = $mtime = time();
        $deferred = new Deferred;
        $priority = \EIO_PRI_DEFAULT;
        \eio_utime($path, $atime, $mtime, $priority, [$this, "onGenericResult"], $deferred);

        return $deferred->getAwaitable();
    }

    /**
     * {@inheritdoc}
     */
    public function get($path) {
        $flags = $flags = \EIO_O_RDONLY;
        $mode = 0;
        $priority = \EIO_PRI_DEFAULT;

        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        \eio_open($path, $flags, $mode, $priority, [$this, "onGetOpen"], $deferred);

        return $deferred->getAwaitable();
    }

    private function onGetOpen($deferred, $result, $req) {
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $priority = \EIO_PRI_DEFAULT;
            \eio_fstat($result, $priority, [$this, "onGetFstat"], [$result, $deferred]);
        }
    }

    private function onGetFstat($fhAndPromisor, $result, $req) {
        list($fh, $deferred) = $fhAndPromisor;
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
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
        list($fh, $deferred) = $fhAndPromisor;
        $priority = \EIO_PRI_DEFAULT;
        \eio_close($fh, $priority, $this->callableDecrementor);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $deferred->resolve($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents) {
        $flags = \EIO_O_RDWR | \EIO_O_CREAT;
        $mode = \EIO_S_IRUSR | \EIO_S_IWUSR | \EIO_S_IXUSR;
        $priority = \EIO_PRI_DEFAULT;

        \call_user_func($this->incrementor, 1);
        $deferred = new Deferred;
        $data = [$contents, $deferred];
        \eio_open($path, $flags, $mode, $priority, [$this, "onPutOpen"], $data);

        return $deferred->getAwaitable();
    }

    private function onPutOpen($data, $result, $req) {
        list($contents, $deferred) = $data;
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $length = strlen($contents);
            $offset = 0;
            $priority = \EIO_PRI_DEFAULT;
            $callback = [$this, "onPutWrite"];
            $fhAndPromisor = [$result, $deferred];
            \eio_write($result, $contents, $length, $offset, $priority, $callback, $fhAndPromisor);
        }
    }

    private function onPutWrite($fhAndPromisor, $result, $req) {
        list($fh, $deferred) = $fhAndPromisor;
        \eio_close($fh);
        $priority = \EIO_PRI_DEFAULT;
        \eio_close($fh, $priority, $this->callableDecrementor);
        if ($result === -1) {
            $deferred->fail(new FilesystemException(
                \eio_get_last_error($req)
            ));
        } else {
            $deferred->resolve($result);
        }
    }

    public function __destruct() {
        Loop::cancel($this->watcher);
    }
}
