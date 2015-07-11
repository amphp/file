<?php

namespace Amp\Fs;

use Amp\{ Reactor, function reactor, Promise, Success, Failure };

class BlockingFilesystem implements Filesystem {
    private $reactor;

    public function __construct(Reactor $reactor = null) {
        $this->reactor = $reactor ?: reactor();
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, int $mode = self::READ): Promise {
        $openMode = 0;

        if ($mode & self::READ && $mode & self::WRITE) {
            $openMode = ($mode & self::CREATE) ? "c+" : "r+";
        } elseif ($mode & self::READ) {
            $openMode = "r";
        } elseif ($mode & self::WRITE) {
            $openMode = "c";
        } else {
            return new Failure(new \InvalidArgumentException(
                "Invalid file open mode: Filesystem::READ or Filesystem::WRITE or both required"
            ));
        }

        if ($fh = @fopen($path, $openMode)) {
            $descriptor = new BlockingDescriptor($fh, $path);
            return new Success($descriptor);
        } else {
            return new Failure(new \RuntimeException(
                "Failed opening file handle"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise {
        if ($stat = @stat($path)) {
            $stat["isfile"] = (bool) is_file($path);
            $stat["isdir"] = empty($stat["isfile"]);
            clearstatcache(true, $path);
        } else {
            $stat = null;
        }

        return new Success($stat);
    }

    /**
     * {@inheritdoc}
     */
    public function lstat(string $path): Promise {
        if ($stat = @lstat($path)) {
            $stat["isfile"] = (bool) is_file($path);
            $stat["isdir"] = empty($stat["isfile"]);
            clearstatcache(true, $path);
        } else {
            $stat = null;
        }

        return new Success($stat);
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link): Promise {
        return new Success((bool) symlink($target, $link));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Promise {
        return new Success((bool) rename($from, $to));
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Promise {
        return new Success((bool) unlink($path));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0644): Promise {
        return new Success((bool) mkdir($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Promise {
        return new Success((bool) rmdir($path));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Promise {
        if ($arr = scandir($path)) {
            $arr = array_values(array_filter($arr, function($el) {
                return !($el === "." || $el === "..");
            }));
            clearstatcache(true, $path);
            return new Success($arr);
        } else {
            return new Failure(new \RuntimeException(
                "Failed reading contents from {$path}"
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise {
        return new Success((bool) chmod($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid, int $gid): Promise {
        if (!@chown($path, $uid)) {
            return new Failure(new \RuntimeException(
                error_get_last()["message"]
            ));
        } elseif (!@chgrp($path, $gid)) {
            return new Failure(new \RuntimeException(
                error_get_last()["message"]
            ));
        } else {
            return new Success;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): Promise {
        $result = @file_get_contents($path);
        return ($result === false)
            ? new Failure(new \RuntimeException(error_get_last()["message"]))
            : new Success($result)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Promise {
        $result = @file_put_contents($path, $contents);
        return ($result === false)
            ? new Failure(new \RuntimeException(error_get_last()["message"]))
            : new Success($result)
        ;
    }
}
