<?php

namespace Amp\File;

use Amp\UvReactor;
use Amp\Success;
use Amp\Failure;
use Amp\Deferred;

class UvDriver implements Driver {
    private $reactor;
    private $loop;

    /**
     * @param \Amp\UvReactor $reactor
     */
    public function __construct(UvReactor $reactor) {
        $this->reactor = $reactor;
        $this->loop = $this->reactor->getLoop();
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode) {
        switch ($mode) {
            case "r":   $flags = \UV::O_RDONLY; break;
            case "r+":  $flags = \UV::O_RDWR; break;
            case "w":   $flags = \UV::O_WRONLY | \UV::O_CREAT; break;
            case "w+":  $flags = \UV::O_RDWR | \UV::O_CREAT; break;
            case "a":   $flags = \UV::O_WRONLY | \UV::O_CREAT | \UV::O_APPEND; break;
            case "a+":  $flags = \UV::O_RDWR | \UV::O_CREAT | \UV::O_APPEND; break;
            case "x":   $flags = \UV::O_WRONLY | \UV::O_CREAT | \UV::O_EXCL; break;
            case "x+":  $flags = \UV::O_RDWR | \UV::O_CREAT | \UV::O_EXCL; break;
            case "c":   $flags = \UV::O_WRONLY | \UV::O_CREAT; break;
            case "c+":  $flags = \UV::O_RDWR | \UV::O_CREAT; break;
            default: return new Failure(new FilesystemException(
                "Invalid open mode"
            ));
        }
        $chmod = ($flags & \UV::O_CREAT) ? 0644 : 0;
        $this->reactor->addRef();
        $promisor = new Deferred;
        $openArr = [$mode, $path, $promisor];
        \uv_fs_open($this->loop, $path, $flags, $chmod, function($fh) use ($openArr) {
            if ($fh) {
                $this->onOpenHandle($fh, $openArr);
            } else {
                $this->reactor->delRef();
                list( , $path, $promisor) = $openArr;
                $promisor->fail(new FilesystemException(
                    "Failed opening file handle to $path"
                ));
            }
        });

        return $promisor->promise();
    }

    private function onOpenHandle($fh, array $openArr) {
        list($mode) = $openArr;
        if ($mode[0] === "w") {
            \uv_fs_ftruncate($this->loop, $fh, $length = 0, function($fh) use ($openArr) {
                $this->reactor->delRef();
                if ($fh) {
                    $this->finalizeHandle($fh, $size = 0, $openArr);
                } else {
                    list( , $path, $promisor) = $openArr;
                    $promisor->fail(new FilesystemException(
                        "Failed truncating file $path"
                    ));
                }
            });
        } else {
            \uv_fs_fstat($this->loop, $fh, function($fh, $stat) use ($openArr) {
                $this->reactor->delRef();
                if ($fh) {
                    StatCache::set($openArr[1], $stat);
                    $this->finalizeHandle($fh, $stat["size"], $openArr);
                } else {
                    list( , $path, $promisor) = $openArr;
                    $promisor->fail(new FilesystemException(
                        "Failed reading file size from open handle pointing to $path"
                    ));
                }
            });
        }
    }

    private function finalizeHandle($fh, $size, array $openArr) {
        list($mode, $path, $promisor) = $openArr;
        $handle = new UvHandle($this->reactor, $fh, $path, $mode, $size);
        $promisor->succeed($handle);
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path) {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_stat($this->loop, $path, function($fh, $stat) use ($promisor, $path) {
            if (empty($fh)) {
                $stat = null;
            } else {
                StatCache::set($path, $stat);
            }
            $this->reactor->delRef();
            $promisor->succeed($stat);
        });

        return $promisor->promise();
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
                $promisor->succeed(!($result["mode"] & \UV::S_IFREG));
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
                $promisor->succeed((bool) ($result["mode"] & \UV::S_IFREG));
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
            } elseif (($result["mode"] & \UV::S_IFREG)) {
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
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_lstat($this->loop, $path, function($fh, $stat) use ($promisor) {
            if (empty($fh)) {
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
    public function symlink($target, $link) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_symlink($this->loop, $target, $link, \UV::S_IRWXU | \UV::S_IRUSR, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function rename($from, $to) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_rename($this->loop, $from, $to, function($fh) use ($promisor, $from) {
            $this->reactor->delRef();
            StatCache::clear($from);
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_unlink($this->loop, $path, function($fh) use ($promisor, $path) {
            $this->reactor->delRef();
            StatCache::clear($path);
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0644) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_mkdir($this->loop, $path, $mode, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_rmdir($this->loop, $path, function($fh) use ($promisor, $path) {
            $this->reactor->delRef();
            StatCache::clear($path);
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function scandir($path) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_readdir($this->loop, $path, 0, function($fh, $data) use ($promisor, $path) {
            $this->reactor->delRef();
            if (empty($fh)) {
                $promisor->fail(new FilesystemException(
                    "Failed reading contents from {$path}"
                ));
            } else {
                $promisor->succeed($data);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode) {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_chmod($this->loop, $path, $mode, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid, $gid) {
        // @TODO Return a failure in windows environments
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_chown($this->loop, $path, $uid, $gid, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function touch($path) {
        $this->reactor->addRef();
        $atime = $mtime = time();
        $promisor = new Deferred;
        \uv_fs_utime($this->loop, $path, $mtime, $atime, function() use ($promisor) {
            // The uv_fs_utime() callback does not receive any args at this time
            $this->reactor->delRef();
            $promisor->succeed(true);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function get($path) {
        return \Amp\resolve($this->doGet($path), $this->reactor);
    }

    private function doGet($path): \Generator {
        $this->reactor->addRef();
        $promise = $this->doFsOpen($path, $flags = \UV::O_RDONLY, $mode = 0);
        if (!$fh = (yield $promise)) {
            $this->reactor->delRef();
            throw new FilesystemException(
                "Failed opening file handle: {$path}"
            );
        }

        $promisor = new Deferred;
        $stat = (yield $this->doFsStat($fh));
        if (empty($stat)) {
            $this->reactor->delRef();
            $promisor->fail(new FilesystemException(
                "stat operation failed on open file handle"
            ));
        } elseif (!$stat["isfile"]) {
            \uv_fs_close($this->loop, $fh, function() use ($promisor) {
                $this->reactor->delRef();
                $promisor->fail(new FilesystemException(
                    "cannot buffer contents: path is not a file"
                ));
            });
        } else {
            $buffer = (yield $this->doFsRead($fh, $offset = 0, $stat["size"]));
            if ($buffer === false ) {
                \uv_fs_close($this->loop, $fh, function() use ($promisor) {
                    $this->reactor->delRef();
                    $promisor->fail(new FilesystemException(
                        "read operation failed on open file handle"
                    ));
                });
            } else {
                \uv_fs_close($this->loop, $fh, function() use ($promisor, $buffer) {
                    $this->reactor->delRef();
                    $promisor->succeed($buffer);
                });
            }
        }

        yield new \Amp\CoroutineResult(yield $promisor->promise());
    }

    private function doFsOpen($path, $flags, $mode) {
        $promisor = new Deferred;
        \uv_fs_open($this->loop, $path, $flags, $mode, function($fh) use ($promisor, $path) {
            $promisor->succeed($fh);
        });

        return $promisor->promise();
    }

    private function doFsStat($fh) {
        $promisor = new Deferred;
        \uv_fs_fstat($this->loop, $fh, function($fh, $stat) use ($promisor) {
            if ($fh) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = !$stat["isdir"];
                $promisor->succeed($stat);
            } else {
                $promisor->succeed();
            }
        });

        return $promisor->promise();
    }

    private function doFsRead($fh, $offset, $len) {
        $promisor = new Deferred;
        \uv_fs_read($this->loop, $fh, $offset, $len, function($fh, $nread, $buffer) use ($promisor) {
            $promisor->succeed(($nread < 0) ? false : $buffer);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents) {
        return \Amp\resolve($this->doPut($path, $contents), $this->reactor);
    }

    private function doPut($path, $contents): \Generator {
        $flags = \UV::O_WRONLY | \UV::O_CREAT;
        $mode = \UV::S_IRWXU | \UV::S_IRUSR;
        $this->reactor->addRef();
        $promise = $this->doFsOpen($path, $flags, $mode);
        if (!$fh = (yield $promise)) {
            $this->reactor->delRef();
            throw new FilesystemException(
                "Failed opening write file handle"
            );
        }

        $promisor = new Deferred;
        $len = strlen($contents);
        \uv_fs_write($this->loop, $fh, $contents, $offset = 0, function($fh, $result) use ($promisor, $len) {
            \uv_fs_close($this->loop, $fh, function() use ($promisor, $result, $len) {
                $this->reactor->delRef();
                if ($result < 0) {
                    $promisor->fail(new FilesystemException(
                        uv_strerror($result)
                    ));
                } else {
                    $promisor->succeed($len);
                }
            });
        });

        yield new \Amp\CoroutineResult(yield $promisor->promise());
    }
}
