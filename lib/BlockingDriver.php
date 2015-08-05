<?php

namespace Amp\File;

use Amp\Promise;
use Amp\Success;
use Amp\Failure;

class BlockingDriver implements Driver {
    /**
     * {@inheritdoc}
     */
    public function stat($path) {
        if ($stat = @\stat($path)) {
            $stat["isfile"] = (bool) \is_file($path);
            $stat["isdir"] = empty($stat["isfile"]);
            \clearstatcache(true, $path);
        } else {
            $stat = null;
        }

        return new Success($stat);
    }

    /**
     * {@inheritdoc}
     */
    public function lstat($path) {
        if ($stat = @\lstat($path)) {
            $stat["isfile"] = (bool) \is_file($path);
            $stat["isdir"] = empty($stat["isfile"]);
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
        return new Success((bool) \symlink($target, $link));
    }

    /**
     * {@inheritdoc}
     */
    public function rename($from, $to) {
        return new Success((bool) \rename($from, $to));
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path) {
        return new Success((bool) \unlink($path));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0644) {
        return new Success((bool) \mkdir($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path) {
        return new Success((bool) \rmdir($path));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir($path) {
        if ($arr = \scandir($path)) {
            $arr = array_values(array_filter($arr, function($el) {
                return !($el === "." || $el === "..");
            }));
            \clearstatcache(true, $path);
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
    public function chmod($path, $mode) {
        return new Success((bool) \chmod($path, $mode));
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid, $gid) {
        if (!@\chown($path, $uid)) {
            return new Failure(new \RuntimeException(
                \error_get_last()["message"]
            ));
        } elseif (!@\chgrp($path, $gid)) {
            return new Failure(new \RuntimeException(
                \error_get_last()["message"]
            ));
        } else {
            return new Success;
        }
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
            ? new Failure(new \RuntimeException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents) {
        $result = @\file_put_contents($path, $contents);
        return ($result === false)
            ? new Failure(new \RuntimeException(\error_get_last()["message"]))
            : new Success($result)
        ;
    }
}
