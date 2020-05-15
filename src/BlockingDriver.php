<?php

namespace Amp\File;

use Amp\Failure;
use Amp\Promise;
use Amp\Success;

final class BlockingDriver implements Driver
{
    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): Promise
    {
        $mode = \str_replace(['b', 't', 'e'], '', $mode);

        switch ($mode) {
            case "r":
            case "r+":
            case "w":
            case "w+":
            case "a":
            case "a+":
            case "x":
            case "x+":
            case "c":
            case "c+":
                break;

            default:
                throw new \Error("Invalid file mode");
        }

        if (!$fh = \fopen($path, $mode . 'be')) {
            return new Failure(new FilesystemException(
                "Failed opening file handle"
            ));
        }

        return new Success(new BlockingFile($fh, $path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise
    {
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
    public function lstat(string $path): Promise
    {
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
    public function symlink(string $target, string $link): Promise
    {
        if (!@\symlink($target, $link)) {
            return new Failure(new FilesystemException("Could not create symbolic link"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function link(string $target, string $link): Promise
    {
        if (!@\link($target, $link)) {
            return new Failure(new FilesystemException("Could not create hard link"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function readlink(string $path): Promise
    {
        if (!($result = @\readlink($path))) {
            return new Failure(new FilesystemException("Could not read symbolic link"));
        }

        return new Success($result);
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Promise
    {
        if (!@\rename($from, $to)) {
            return new Failure(new FilesystemException("Could not rename file"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Promise
    {
        StatCache::clear($path);

        if (!@\unlink($path)) {
            return new Failure(new FilesystemException("Could not unlink file"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0777, bool $recursive = false): Promise
    {
        if (!@\mkdir($path, $mode, $recursive)) {
            return new Failure(new FilesystemException("Could not create directory"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Promise
    {
        StatCache::clear($path);

        if (!@\rmdir($path)) {
            return new Failure(new FilesystemException("Could not remove directory"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Promise
    {
        if (!@\is_dir($path)) {
            return new Failure(new FilesystemException(
                "Not a directory"
            ));
        } elseif ($arr = @\scandir($path)) {
            $arr = \array_values(\array_filter($arr, function ($el) {
                return !($el === "." || $el === "..");
            }));
            \clearstatcache(true, $path);
            return new Success($arr);
        }

        return new Failure(new FilesystemException(
            "Failed reading contents from {$path}"
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise
    {
        if (!@\chmod($path, $mode)) {
            return new Failure(new FilesystemException("Could not change file permissions"));
        }

        StatCache::clear();

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, ?int $uid, ?int $gid = null): Promise
    {
        if (($uid ?? -1) !== -1 && !@\chown($path, $uid)) {
            $message = 'Could not open the file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }

            return new Failure(new FilesystemException($message));
        }

        if (($gid ?? -1) !== -1 && !@\chgrp($path, $gid)) {
            $message = 'Could not open the file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }

            return new Failure(new FilesystemException($message));
        }

        StatCache::clear();

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function touch(string $path, ?int $time = null, ?int $atime = null): Promise
    {
        $time = $time ?? \time();
        $atime = $atime ?? $time;
        if (! @\touch($path, $time, $atime)) {
            $message = 'Could not touch file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            return new Failure(new FilesystemException($message));
        }

        StatCache::clear();

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): Promise
    {
        $result = @\file_get_contents($path);
        if ($result === false) {
            $message = 'Could not open the file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            return new Failure(new FilesystemException($message));
        }

        return new Success($result);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Promise
    {
        $result = @\file_put_contents($path, $contents);
        if ($result === false) {
            $message = 'Could not open the file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            return new Failure(new FilesystemException($message));
        }

        return new Success();
    }
}
