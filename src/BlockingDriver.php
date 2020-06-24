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
    public function openFile(string $path, string $mode): Promise
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
    public function getStatus(string $path): Promise
    {
        if ($stat = StatCache::get($path)) {
            return new Success($stat);
        }

        if ($stat = @\stat($path)) {
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
    public function getLinkStatus(string $path): Promise
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
    public function createSymlink(string $target, string $link): Promise
    {
        if (!@\symlink($target, $link)) {
            return new Failure(new FilesystemException("Could not create symbolic link"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function createHardlink(string $target, string $link): Promise
    {
        if (!@\link($target, $link)) {
            return new Failure(new FilesystemException("Could not create hard link"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function resolveSymlink(string $path): Promise
    {
        if (!($result = @\readlink($path))) {
            return new Failure(new FilesystemException("Could not read symbolic link"));
        }

        return new Success($result);
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): Promise
    {
        if (!@\rename($from, $to)) {
            return new Failure(new FilesystemException("Could not rename file {$from} to {$to}"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(string $path): Promise
    {
        StatCache::clear($path);

        if (!@\unlink($path)) {
            return new Failure(new FilesystemException("Could not delete file: {$path}"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function createDirectory(string $path, int $mode = 0777, bool $recursive = false): Promise
    {
        if (!@\mkdir($path, $mode, $recursive)) {
            return new Failure(new FilesystemException("Could not create directory"));
        }

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $path): Promise
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
    public function listFiles(string $path): Promise
    {
        if (!@\is_dir($path)) {
            return new Failure(new FilesystemException(
                "Not a directory: {$path}"
            ));
        }

        if ($arr = @\scandir($path)) {
            \clearstatcache(true, $path);

            return new Success(\array_values(\array_filter($arr, static function ($el) {
                return !($el === "." || $el === "..");
            })));
        }

        return new Failure(new FilesystemException(
            "Failed reading contents from {$path}"
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function changePermissions(string $path, int $mode): Promise
    {
        if (!@\chmod($path, $mode)) {
            return new Failure(new FilesystemException("Could not change file permissions"));
        }

        StatCache::clear($path);

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
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

        StatCache::clear($path);

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function touch(string $path, ?int $time, ?int $atime): Promise
    {
        $time = $time ?? \time();
        $atime = $atime ?? $time;
        if (!@\touch($path, $time, $atime)) {
            $message = 'Could not touch file.';
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            return new Failure(new FilesystemException($message));
        }

        StatCache::clear($path);

        return new Success();
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): Promise
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
    public function write(string $path, string $contents): Promise
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
