<?php

namespace Amp\File;

use Amp\Success;
use Amp\Failure;

class BlockingDriver implements Driver {
    /**
     * {@inheritdoc}
     */
    public function open($path, $mode) {
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
    public function stat($path) {
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
    public function exists($path) {
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
     * @return \Amp\Promise<int>
     */
    public function size($path) {
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
     * @return \Amp\Promise<bool>
     */
    public function isdir($path) {
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
     * @return \Amp\Promise<bool>
     */
    public function isfile($path) {
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
     * @return \Amp\Promise<int>
     */
    public function mtime($path) {
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
     * @return \Amp\Promise<int>
     */
    public function atime($path) {
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
     * @return \Amp\Promise<int>
     */
    public function ctime($path) {
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
    public function lstat($path) {
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
    public function symlink($target, $link) {
        return new Success((bool) @\symlink($target, $link));
    }

    /**
     * {@inheritdoc}
     */
    public function rename($from, $to) {
        return new Success((bool) @\rename($from, $to));
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path) {
        StatCache::clear($path);
        return new Success((bool) @\unlink($path));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0644) {
        return new Success((bool) @\mkdir($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path) {
        StatCache::clear($path);
        return new Success((bool) @\rmdir($path));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir($path) {
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
    public function chmod($path, $mode) {
        return new Success((bool) @\chmod($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid, $gid) {
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
    public function touch($path) {
        return new Success((bool) \touch($path));
    }

    /**
     * {@inheritdoc}
     */
    public function get($path) {
        $result = @\file_get_contents($path);
        return ($result === false)
            ? new Failure(new FilesystemException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents) {
        $result = @\file_put_contents($path, $contents);
        return ($result === false)
            ? new Failure(new FilesystemException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }
}
