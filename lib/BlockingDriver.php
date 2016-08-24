<?php declare(strict_types = 1);

namespace Amp\File;

use Amp\{ Success, Failure };
use Interop\Async\Awaitable;

class BlockingDriver implements Driver {
    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): Awaitable {
        if (!$fh = \fopen($path, $mode)) {
            return new Failure(new FilesystemException(
                "Failed opening file handle"
            ));
        }

        return new Success(new BlockingHandle($fh, $path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Awaitable {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        } elseif ($stat = @\stat($path)) {
            StatCache::set($path, $stat);
            \clearstatcache(true, $path);
        } else {
            $stat = null;
        }

        return new Success($stat);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): Awaitable {
        if ($exists = @\file_exists($path)) {
            \clearstatcache(true, $path);
        }
        return new Success($exists);
    }

    /**
     * Retrieve the size in bytes of the file at the specified path.
     *
     * If the path does not exist or is not a regular file this
     * function's returned Promise WILL resolve as a failure.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function size(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Failure(new FilesystemException(
                "Path does not exist"
            ));
        } elseif (!@\is_file($path)) {
            return new Failure(new FilesystemException(
                "Path is not a regular file"
            ));
        } elseif (($size = @\filesize($path)) === false) {
            return new Failure(new FilesystemException(
                \error_get_last()["message"]
            ));
        } else {
            \clearstatcache(true, $path);
            return new Success($size);
        }
    }

    /**
     * Does the specified path exist and is it a directory?
     *
     * If the path does not exist the returned Promise will resolve
     * to FALSE. It will NOT reject with an error.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<bool>
     */
    public function isdir(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Success(false);
        }
        $isDir = @\is_dir($path);
        \clearstatcache(true, $path);

        return new Success($isDir);
    }

    /**
     * Does the specified path exist and is it a file?
     *
     * If the path does not exist the returned Promise will resolve
     * to FALSE. It will NOT reject with an error.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<bool>
     */
    public function isfile(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Success(false);
        }
        $isFile = @\is_file($path);
        \clearstatcache(true, $path);

        return new Success($isFile);
    }

    /**
     * Retrieve the path's last modification time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function mtime(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Failure(new FilesystemException(
                "Path does not exist"
            ));
        }
        $mtime = @\filemtime($path);
        \clearstatcache(true, $path);

        return new Success($mtime);
    }

    /**
     * Retrieve the path's last access time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function atime(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Failure(new FilesystemException(
                "Path does not exist"
            ));
        }
        $atime = @\fileatime($path);
        \clearstatcache(true, $path);

        return new Success($atime);
    }

    /**
     * Retrieve the path's creation time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function ctime(string $path): Awaitable {
        if (!@\file_exists($path)) {
            return new Failure(new FilesystemException(
                "Path does not exist"
            ));
        }
        $ctime = @\filectime($path);
        \clearstatcache(true, $path);

        return new Success($ctime);
    }

    /**
     * {@inheritdoc}
     */
    public function lstat(string $path): Awaitable {
        if ($stat = @\lstat($path)) {
            \clearstatcache(true, $path);
        } else {
            $stat = null;
        }

        return new Success($stat);
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link): Awaitable {
        if (!@\symlink($target, $link)) {
            return new Failure(new FilesystemException("Could not create symbolic link"));
        }
    
        return new Success(true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function link(string $target, string $link): Awaitable {
        if (!@\link($target, $link)) {
            return new Failure(new FilesystemException("Could not create hard link"));
        }
        
        return new Success(true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function readlink(string $path): Awaitable {
        if (!($result = @\readlink($path))) {
            return new Failure(new FilesystemException("Could not read symbolic link"));
        }
        
        return new Success($result);
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Awaitable {
        if (!@\rename($from, $to)) {
            return new Failure(new FilesystemException("Could not rename file"));
        }
    
        return new Success(true);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Awaitable {
        StatCache::clear($path);
        return new Success((bool) @\unlink($path));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0644): Awaitable {
        return new Success((bool) @\mkdir($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Awaitable {
        StatCache::clear($path);
        return new Success((bool) @\rmdir($path));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Awaitable {
        if (!@\is_dir($path)) {
            return new Failure(new FilesystemException(
                "Not a directory"
            ));
        } elseif ($arr = @\scandir($path)) {
            $arr = \array_values(\array_filter($arr, function($el) {
                return !($el === "." || $el === "..");
            }));
            \clearstatcache(true, $path);
            return new Success($arr);
        } else {
            return new Failure(new FilesystemException(
                "Failed reading contents from {$path}"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Awaitable {
        return new Success((bool) @\chmod($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid, int $gid): Awaitable {
        if ($uid !== -1 && !@\chown($path, $uid)) {
            return new Failure(new FilesystemException(
                \error_get_last()["message"]
            ));
        }

        if ($gid !== -1 && !@\chgrp($path, $gid)) {
            return new Failure(new FilesystemException(
                \error_get_last()["message"]
            ));
        }

        return new Success;
    }

    /**
     * {@inheritdoc}
     */
    public function touch(string $path): Awaitable {
        return new Success((bool) \touch($path));
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): Awaitable {
        $result = @\file_get_contents($path);
        return ($result === false)
            ? new Failure(new FilesystemException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Awaitable {
        $result = @\file_put_contents($path, $contents);
        return ($result === false)
            ? new Failure(new FilesystemException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }
}
