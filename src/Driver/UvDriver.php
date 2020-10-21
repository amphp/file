<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\File\Driver;

use Amp\Coroutine;
use Amp\Deferred;
use Amp\File\Driver;
use Amp\File\FilesystemException;
use Amp\File\Internal;
use Amp\Loop;
use Amp\Promise;
use Closure;

final class UvDriver implements Driver
{
    /**
     * @param Loop\Driver $driver The currently active loop driver.
     *
     * @return bool Determines if this driver can be used based on the environment.
     */
    public static function isSupported(Loop\Driver $driver): bool
    {
        return $driver instanceof Loop\UvDriver;
    }

    /** @var Loop\UvDriver */
    private $driver;
    /** @var \UVLoop|resource Loop resource of type uv_loop or instance of \UVLoop. */
    private $loop;
    /** @var Internal\UvPoll */
    private $poll;
    /** @var bool True if ext-uv version is < 0.3.0. */
    private $priorVersion;

    public function __construct(Loop\UvDriver $driver)
    {
        $this->driver = $driver;
        $this->loop = $driver->getHandle();
        $this->poll = new Internal\UvPoll($driver);
        $this->priorVersion = \version_compare(\phpversion('uv'), '0.3.0', '<');
    }

    public function openFile(string $path, string $mode): Promise
    {
        $flags = $this->parseMode($mode);
        $chmod = ($flags & \UV::O_CREAT) ? 0644 : 0;

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $openArr = [$mode, $path, $deferred];
        \uv_fs_open($this->loop, $path, $flags, $chmod, function ($fh) use ($openArr): void {
            if (\is_resource($fh)) {
                $this->onOpenHandle($fh, $openArr);
            } else {
                [, $path, $deferred] = $openArr;
                $deferred->fail(new FilesystemException("Failed opening file '{$path}'"));
            }
        });

        return $deferred->promise();
    }

    public function getStatus(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $callback = static function ($stat) use ($deferred, $path): void {
            if (\is_int($stat)) {
                $deferred->resolve(null);
                return;
            }

            // link is not a valid stat type but returned by the uv extension
            // change link to nlink
            if (isset($stat['link'])) {
                $stat['nlink'] = $stat['link'];

                unset($stat['link']);
            }

            $deferred->resolve($stat);
        };

        if ($this->priorVersion) {
            $callback = static function ($fh, $stat) use ($callback): void {
                if (empty($fh)) {
                    $stat = 0;
                }

                $callback($stat);
            };
        }

        \uv_fs_stat($this->loop, $path, $callback);

        return $deferred->promise();
    }

    public function getLinkStatus(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        if ($this->priorVersion) {
            $callback = static function ($fh, $stat) use ($deferred): void {
                $deferred->resolve(empty($fh) ? null : $stat);
            };
        } else {
            $callback = static function ($stat) use ($deferred): void {
                $deferred->resolve(\is_int($stat) ? null : $stat);
            };
        }

        \uv_fs_lstat($this->loop, $path, $callback);

        return $deferred->promise();
    }

    public function createSymlink(string $target, string $link): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $callback = $this->createGenericCallback($deferred, "Could not create symbolic link");
        \uv_fs_symlink($this->loop, $target, $link, \UV::S_IRWXU | \UV::S_IRUSR, $callback);

        return $deferred->promise();
    }

    public function createHardlink(string $target, string $link): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_link($this->loop, $target, $link, $this->createGenericCallback($deferred, "Could not create hard link"));

        return $deferred->promise();
    }

    public function resolveSymlink(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        if ($this->priorVersion) {
            $callback = static function ($fh, $target) use ($deferred): void {
                if (!(bool) $fh) {
                    $deferred->fail(new FilesystemException("Could not read symbolic link"));
                    return;
                }

                $deferred->resolve($target);
            };
        } else {
            $callback = static function ($target) use ($deferred): void {
                if (\is_int($target)) {
                    $deferred->fail(new FilesystemException("Could not read symbolic link"));
                    return;
                }

                $deferred->resolve($target);
            };
        }

        \uv_fs_readlink($this->loop, $path, $callback);

        return $deferred->promise();
    }

    public function move(string $from, string $to): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_rename($this->loop, $from, $to, $this->createGenericCallback($deferred, "Could not rename file"));

        return $deferred->promise();
    }

    public function deleteFile(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_unlink($this->loop, $path, $this->createGenericCallback($deferred, "Could not unlink file"));

        return $deferred->promise();
    }

    public function createDirectory(string $path, int $mode = 0777): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_mkdir($this->loop, $path, $mode, $this->createGenericCallback($deferred, "Could not create directory"));

        return $deferred->promise();
    }

    public function createDirectoryRecursively(string $path, int $mode = 0777): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $path = \str_replace("/", DIRECTORY_SEPARATOR, $path);
        $arrayPath = \explode(DIRECTORY_SEPARATOR, $path);
        $tmpPath = "";

        $callback = function () use (
            &$callback,
            &$arrayPath,
            &$tmpPath,
            $mode,
            $deferred
        ) {
            $tmpPath .= DIRECTORY_SEPARATOR . \array_shift($arrayPath);

            if (empty($arrayPath)) {
                \uv_fs_mkdir(
                    $this->loop,
                    $tmpPath,
                    $mode,
                    $this->createGenericCallback($deferred, "Could not create directory")
                );
            } else {
                $this->isDir($tmpPath)->onResolve(function ($error, $result) use (
                    $callback,
                    $tmpPath,
                    $mode
                ): void {
                    if ($result) {
                        $callback();
                    } else {
                        \uv_fs_mkdir($this->loop, $tmpPath, $mode, $callback);
                    }
                });
            }
        };

        $callback();

        return $deferred->promise();
    }

    public function deleteDirectory(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        \uv_fs_rmdir($this->loop, $path, $this->createGenericCallback($deferred, "Could not remove directory"));

        return $deferred->promise();
    }

    public function listFiles(string $path): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        if ($this->priorVersion) {
            \uv_fs_readdir($this->loop, $path, 0, static function ($fh, $data) use ($deferred, $path): void {
                if (empty($fh) && $data !== 0) {
                    $deferred->fail(new FilesystemException("Failed reading contents from {$path}"));
                } elseif ($data === 0) {
                    $deferred->resolve([]);
                } else {
                    $deferred->resolve($data);
                }
            });
        } else {
            /** @noinspection PhpUndefinedFunctionInspection */
            \uv_fs_scandir($this->loop, $path, static function ($data) use ($deferred, $path): void {
                if (\is_int($data) && $data !== 0) {
                    $deferred->fail(new FilesystemException("Failed reading contents from {$path}"));
                } elseif ($data === 0) {
                    $deferred->resolve([]);
                } else {
                    $deferred->resolve($data);
                }
            });
        }

        return $deferred->promise();
    }

    public function changePermissions(string $path, int $mode): Promise
    {
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $callback = $this->createGenericCallback($deferred, "Could not change file permissions");
        \uv_fs_chmod($this->loop, $path, $mode, $callback);

        return $deferred->promise();
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
    {
        // @TODO Return a failure in windows environments
        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $callback = $this->createGenericCallback($deferred, "Could not change file owner");
        \uv_fs_chown($this->loop, $path, $uid ?? -1, $gid ?? -1, $callback);

        return $deferred->promise();
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): Promise
    {
        $modificationTime = $modificationTime ?? \time();
        $accessTime = $accessTime ?? $modificationTime;

        $deferred = new Deferred;
        $this->poll->listen($deferred->promise());

        $callback = $this->createGenericCallback($deferred, "Could not touch file");
        \uv_fs_utime($this->loop, $path, $modificationTime, $accessTime, $callback);

        return $deferred->promise();
    }

    public function read(string $path): Promise
    {
        $promise = new Coroutine($this->doRead($path));
        $this->poll->listen($promise);

        return $promise;
    }

    public function write(string $path, string $contents): Promise
    {
        $promise = new Coroutine($this->doWrite($path, $contents));
        $this->poll->listen($promise);

        return $promise;
    }

    private function parseMode(string $mode): int
    {
        $mode = \str_replace(['b', 't', 'e'], '', $mode);

        switch ($mode) {
            case "r":
                return \UV::O_RDONLY;
            case "r+":
                return \UV::O_RDWR;
            case "c":
            case "w":
                return \UV::O_WRONLY | \UV::O_CREAT;
            case "c+":
            case "w+":
                return \UV::O_RDWR | \UV::O_CREAT;
            case "a":
                return \UV::O_WRONLY | \UV::O_CREAT | \UV::O_APPEND;
            case "a+":
                return \UV::O_RDWR | \UV::O_CREAT | \UV::O_APPEND;
            case "x":
                return \UV::O_WRONLY | \UV::O_CREAT | \UV::O_EXCL;
            case "x+":
                return \UV::O_RDWR | \UV::O_CREAT | \UV::O_EXCL;
            default:
                throw new \Error('Invalid file mode');
        }
    }

    private function onOpenHandle($fh, array $openArr): void
    {
        [$mode] = $openArr;

        if ($mode[0] === "w") {
            \uv_fs_ftruncate($this->loop, $fh, $length = 0, function ($fh) use ($openArr): void {
                if (\is_resource($fh)) {
                    $this->finalizeHandle($fh, $size = 0, $openArr);
                } else {
                    [, $path, $deferred] = $openArr;
                    $deferred->fail(new FilesystemException(
                        "Failed truncating file $path"
                    ));
                }
            });
        } else {
            \uv_fs_fstat($this->loop, $fh, function ($fh, $stat) use ($openArr): void {
                if (\is_resource($fh)) {
                    $this->finalizeHandle($fh, $stat["size"], $openArr);
                } else {
                    [, $path, $deferred] = $openArr;
                    $deferred->fail(new FilesystemException(
                        "Failed reading file size from open handle pointing to $path"
                    ));
                }
            });
        }
    }

    private function finalizeHandle($fh, $size, array $openArr): void
    {
        [$mode, $path, $deferred] = $openArr;
        $handle = new UvFile($this->driver, $this->poll, $fh, $path, $mode, $size);
        $deferred->resolve($handle);
    }

    private function isDir(string $path): Promise
    {
        $deferred = new Deferred;

        $this->getStatus($path)->onResolve(static function ($error, $result) use ($deferred): void {
            if ($result) {
                $deferred->resolve(($result["mode"] & \UV::S_IFDIR) === \UV::S_IFDIR);
            } else {
                $deferred->resolve(false);
            }
        });

        return $deferred->promise();
    }

    private function doRead($path): \Generator
    {
        $promise = $this->doFsOpen($path, $flags = \UV::O_RDONLY, $mode = 0);
        if (!$fh = yield $promise) {
            throw new FilesystemException("Failed opening file handle: {$path}");
        }

        $deferred = new Deferred;

        $stat = yield $this->doFsStat($fh);

        if (empty($stat)) {
            $deferred->fail(new FilesystemException("stat operation failed on open file handle"));
        } elseif (!$stat["isfile"]) {
            \uv_fs_close($this->loop, $fh, static function () use ($deferred): void {
                $deferred->fail(new FilesystemException("cannot buffer contents: path is not a file"));
            });
        } else {
            $buffer = yield $this->doFsRead($fh, $offset = 0, $stat["size"]);

            if ($buffer === false) {
                \uv_fs_close($this->loop, $fh, static function () use ($deferred): void {
                    $deferred->fail(new FilesystemException("read operation failed on open file handle"));
                });
            } else {
                \uv_fs_close($this->loop, $fh, static function () use ($deferred, $buffer): void {
                    $deferred->resolve($buffer);
                });
            }
        }

        return yield $deferred->promise();
    }

    private function doFsOpen(string $path, int $flags, int $mode): Promise
    {
        $deferred = new Deferred;

        \uv_fs_open($this->loop, $path, $flags, $mode, static function ($fh) use ($deferred) {
            $deferred->resolve($fh);
        });

        return $deferred->promise();
    }

    private function doFsStat($fh): Promise
    {
        $deferred = new Deferred;

        \uv_fs_fstat($this->loop, $fh, static function ($fh, $stat) use ($deferred): void {
            if (\is_resource($fh)) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = !$stat["isdir"];
                $deferred->resolve($stat);
            } else {
                $deferred->resolve();
            }
        });

        return $deferred->promise();
    }

    private function doFsRead($fh, int $offset, int $len): Promise
    {
        $deferred = new Deferred;

        if ($this->priorVersion) {
            $callback = static function ($fh, $readBytes, $buffer) use ($deferred): void {
                $deferred->resolve($readBytes < 0 ? false : $buffer);
            };
        } else {
            $callback = static function ($readBytes, $buffer) use ($deferred): void {
                $deferred->resolve($readBytes < 0 ? false : $buffer);
            };
        }

        \uv_fs_read($this->loop, $fh, $offset, $len, $callback);

        return $deferred->promise();
    }

    private function doWrite(string $path, string $contents): \Generator
    {
        $flags = \UV::O_WRONLY | \UV::O_CREAT;
        $mode = \UV::S_IRWXU | \UV::S_IRUSR;

        $promise = $this->doFsOpen($path, $flags, $mode);

        if (!$fh = yield $promise) {
            throw new FilesystemException("Failed opening write file handle");
        }

        $deferred = new Deferred;

        \uv_fs_write($this->loop, $fh, $contents, $offset = 0, function ($fh, $result) use ($deferred): void {
            \uv_fs_close($this->loop, $fh, static function () use ($deferred, $result): void {
                if ($result < 0) {
                    $deferred->fail(new FilesystemException(\uv_strerror($result)));
                } else {
                    $deferred->resolve();
                }
            });
        });

        return yield $deferred->promise();
    }

    private function createGenericCallback(Deferred $deferred, string $error): Closure
    {
        $callback = static function (int $result) use ($deferred, $error): void {
            if ($result !== 0) {
                $deferred->fail(new FilesystemException($error));
                return;
            }

            $deferred->resolve();
        };

        if ($this->priorVersion) {
            $callback = static function (bool $result) use ($callback): void {
                $callback($result ? 0 : -1);
            };
        }

        return $callback;
    }
}
