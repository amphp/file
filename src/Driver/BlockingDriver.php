<?php

namespace Amp\File\Driver;

use Amp\Failure;
use Amp\File\Driver;
use Amp\File\FilesystemException;
use Amp\Promise;
use Amp\Success;

final class BlockingDriver implements Driver
{
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
                throw new \Error("Invalid file mode: {$mode}");
        }

        try {
            \set_error_handler(static function ($type, $message) use ($path, $mode) {
                throw new FilesystemException("Failed to open '{$path}' in mode '{$mode}': {$message}");
            });

            if (!$handle = \fopen($path, $mode . 'be')) {
                throw new FilesystemException("Failed to open '{$path}' in mode '{$mode}'");
            }

            return new Success(new BlockingFile($handle, $path, $mode));
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function getStatus(string $path): Promise
    {
        \clearstatcache(true, $path);

        return new Success(@\stat($path) ?: null);
    }

    public function getLinkStatus(string $path): Promise
    {
        \clearstatcache(true, $path);

        return new Success(@\lstat($path) ?: null);
    }

    public function createSymlink(string $target, string $link): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($target, $link) {
                throw new FilesystemException("Could not create symbolic link '{$link}' to '{$target}': {$message}");
            });

            if (!\symlink($target, $link)) {
                throw new FilesystemException("Could not create symbolic link '{$link}' to '{$target}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function createHardlink(string $target, string $link): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($target, $link) {
                throw new FilesystemException("Could not create hard link '{$link}' to '{$target}': {$message}");
            });

            if (!\link($target, $link)) {
                throw new FilesystemException("Could not create hard link '{$link}' to '{$target}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function resolveSymlink(string $path): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Could not resolve symbolic link '{$path}': {$message}");
            });

            if (false === ($result = \readlink($path))) {
                throw new FilesystemException("Could not resolve symbolic link '{$path}'");
            }

            return new Success($result);
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function move(string $from, string $to): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($from, $to) {
                throw new FilesystemException("Could not move file from '{$from}' to '{$to}': {$message}");
            });

            if (!\rename($from, $to)) {
                throw new FilesystemException("Could not move file from '{$from}' to '{$to}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function deleteFile(string $path): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Could not delete file '{$path}': {$message}");
            });

            if (!\unlink($path)) {
                throw new FilesystemException("Could not delete file '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function createDirectory(string $path, int $mode = 0777): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Could not create directory '{$path}': {$message}");
            });

            /** @noinspection MkdirRaceConditionInspection */
            if (!\mkdir($path, $mode)) {
                throw new FilesystemException("Could not create directory '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function createDirectoryRecursively(string $path, int $mode = 0777): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                if (!\is_dir($path)) {
                    throw new FilesystemException("Could not create directory '{$path}': {$message}");
                }
            });

            if (\is_dir($path)) {
                return new Success;
            }

            /** @noinspection MkdirRaceConditionInspection */
            if (!\mkdir($path, $mode, true)) {
                if (\is_dir($path)) {
                    return new Success;
                }

                throw new FilesystemException("Could not create directory '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function deleteDirectory(string $path): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Could not remove directory '{$path}': {$message}");
            });

            if (!\rmdir($path)) {
                throw new FilesystemException("Could not remove directory '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function listFiles(string $path): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to list files in '{$path}': {$message}");
            });

            if (!\is_dir($path)) {
                throw new FilesystemException("Failed to list files; '{$path}' is not a directory");
            }

            if ($arr = \scandir($path)) {
                \clearstatcache(true, $path);

                return new Success(\array_values(\array_filter($arr, static function ($el) {
                    return $el !== "." && $el !== "..";
                })));
            }

            throw new FilesystemException("Failed to list files in '{$path}'");
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function changePermissions(string $path, int $mode): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to change permissions for '{$path}': {$message}");
            });

            if (!\chmod($path, $mode)) {
                throw new FilesystemException("Failed to change permissions for '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to change owner for '{$path}': {$message}");
            });

            if (($uid ?? -1) !== -1 && !\chown($path, $uid)) {
                throw new FilesystemException("Failed to change owner for '{$path}'");
            }

            if (($gid ?? -1) !== -1 && !\chgrp($path, $gid)) {
                throw new FilesystemException("Failed to change owner for '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to touch '{$path}': {$message}");
            });

            $modificationTime = $modificationTime ?? \time();
            $accessTime = $accessTime ?? $modificationTime;

            if (!\touch($path, $modificationTime, $accessTime)) {
                throw new FilesystemException("Failed to touch '{$path}'");
            }

            return new Success;
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function read(string $path): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to read '{$path}': {$message}");
            });

            if (false === ($result = \file_get_contents($path))) {
                throw new FilesystemException("Failed to read '{$path}'");
            }

            return new Success($result);
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }

    public function write(string $path, string $contents): Promise
    {
        try {
            \set_error_handler(static function ($type, $message) use ($path) {
                throw new FilesystemException("Failed to read '{$path}': {$message}");
            });

            if (false === ($result = \file_put_contents($path, $contents))) {
                throw new FilesystemException("Failed to read '{$path}'");
            }

            return new Success($result);
        } catch (FilesystemException $e) {
            return new Failure($e);
        } finally {
            \restore_error_handler();
        }
    }
}
