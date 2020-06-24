<?php

namespace Amp\File;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

const LOOP_STATE_IDENTIFIER = Driver::class;

/**
 * Retrieve the application-wide filesystem instance.
 *
 * @param Driver|null $driver Use the specified object as the application-wide filesystem instance.
 *
 * @return Driver
 */
function filesystem(?Driver $driver = null): Driver
{
    if ($driver === null) {
        $driver = Loop::getState(LOOP_STATE_IDENTIFIER);
        if ($driver) {
            return $driver;
        }

        $driver = createDefaultDriver();
    }

    if (\defined("AMP_WORKER") && $driver instanceof ParallelDriver) {
        throw new \Error("Cannot use the parallel driver within a worker");
    }

    Loop::setState(LOOP_STATE_IDENTIFIER, $driver);
    return $driver;
}

/**
 * Create a new filesystem driver best-suited for the current environment.
 *
 * @return Driver
 */
function createDefaultDriver(): Driver
{
    $driver = Loop::get();

    if (UvDriver::isSupported($driver)) {
        /** @var Loop\UvDriver $driver */
        return new UvDriver($driver);
    }

    if (EioDriver::isSupported()) {
        return new EioDriver;
    }

    if (\defined("AMP_WORKER")) { // Prevent spawning infinite workers.
        return new BlockingDriver;
    }

    return new ParallelDriver;
}

/**
 * Open a handle for the specified path.
 *
 * @param string $path
 * @param string $mode
 *
 * @return Promise<File>
 */
function open(string $path, string $mode): Promise
{
    return filesystem()->openFile($path, $mode);
}

/**
 * Execute a file stat operation.
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise should never resolve as a failure.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<array|null>
 */
function stat(string $path): Promise
{
    return filesystem()->getStatus($path);
}

/**
 * Does the specified path exist?
 *
 * This function should never resolve as a failure -- only a successful bool value
 * indicating the existence of the specified path.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<bool>
 */
function exists(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred): void {
        $deferred->resolve($result !== null);
    });

    return $deferred->promise();
}

/**
 * Retrieve the size in bytes of the file at the specified path.
 *
 * If the path does not exist or is not a regular file this
 * function's returned Promise WILL resolve as a failure.
 *
 * @param string $path An absolute file system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist or is not a file.
 *
 * @return Promise<int>
 */
function size(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred, $path): void {
        if ($result === null) {
            $deferred->fail(new FilesystemException(
                "Specified path does not exist: {$path}",
                $error
            ));
        } elseif (($result["mode"] & 0100000) === 0100000) {
            $deferred->resolve($result["size"]);
        } else {
            $deferred->fail(new FilesystemException(
                "Specified path is not a regular file: {$path}",
                $error
            ));
        }
    });

    return $deferred->promise();
}

/**
 * Does the specified path exist and is it a directory?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<bool>
 */
function isDir(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred): void {
        if ($result !== null) {
            $deferred->resolve(($result["mode"] & 0040000) === 0040000);
        } else {
            $deferred->resolve(false);
        }
    });

    return $deferred->promise();
}

/**
 * Does the specified path exist and is it a file?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<bool>
 */
function isFile(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred): void {
        if ($result !== null) {
            $deferred->resolve(($result["mode"] & 0100000) === 0100000);
        } else {
            $deferred->resolve(false);
        }
    });

    return $deferred->promise();
}

/**
 * Does the specified path exist and is it a symlink?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<bool>
 */
function isSymlink(string $path): Promise
{
    $deferred = new Deferred;

    lstat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred): void {
        if ($result !== null) {
            $deferred->resolve(($result["mode"] & 0120000) === 0120000);
        } else {
            $deferred->resolve(false);
        }
    });

    return $deferred->promise();
}

/**
 * Retrieve the path's last modification time as a unix timestamp.
 *
 * @param string $path An absolute file system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 *
 * @return Promise<int>
 */
function mtime(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred, $path): void {
        if ($result !== null) {
            $deferred->resolve($result["mtime"]);
        } else {
            $deferred->fail(new FilesystemException(
                "Specified path does not exist: {$path}",
                $error
            ));
        }
    });

    return $deferred->promise();
}

/**
 * Retrieve the path's last access time as a unix timestamp.
 *
 * @param string $path An absolute file system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 *
 * @return Promise<int>
 */
function atime(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred, $path): void {
        if ($result !== null) {
            $deferred->resolve($result["atime"]);
        } else {
            $deferred->fail(new FilesystemException(
                "Specified path does not exist: {$path}",
                $error
            ));
        }
    });

    return $deferred->promise();
}

/**
 * Retrieve the path's creation time as a unix timestamp.
 *
 * @param string $path An absolute file system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 *
 * @return Promise<int>
 */
function ctime(string $path): Promise
{
    $deferred = new Deferred;

    stat($path)->onResolve(static function (?\Throwable $error, ?array $result) use ($deferred, $path): void {
        if ($result !== null) {
            $deferred->resolve($result["ctime"]);
        } else {
            $deferred->fail(new FilesystemException(
                "Specified path does not exist: {$path}",
                $error
            ));
        }
    });

    return $deferred->promise();
}

/**
 * Same as stat() except if the path is a link then the link's data is returned.
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise should never resolve as a failure.
 *
 * @param string $path An absolute file system path.
 *
 * @return Promise<array|null>
 */
function lstat(string $path): Promise
{
    return filesystem()->getLinkStatus($path);
}

/**
 * Create a symlink $link pointing to the file/directory located at $original.
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function symlink(string $original, string $link): Promise
{
    return filesystem()->createSymlink($original, $link);
}

/**
 * Create a hard link $link pointing to the file/directory located at $original.
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function link(string $original, string $link): Promise
{
    return filesystem()->createHardlink($original, $link);
}

/**
 * Read the symlink at $path.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<string>
 */
function readLink(string $path): Promise
{
    return filesystem()->resolveSymlink($path);
}

/**
 * Rename a file or directory.
 *
 * @param string $from
 * @param string $to
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function rename(string $from, string $to): Promise
{
    return filesystem()->move($from, $to);
}

/**
 * Delete a file.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function unlink(string $path): Promise
{
    return filesystem()->deleteFile($path);
}

/**
 * Create a directory.
 *
 * @param string $path
 * @param int    $mode
 * @param bool   $recursive
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function mkdir(string $path, int $mode = 0777, bool $recursive = false): Promise
{
    return filesystem()->createDirectory($path, $mode, $recursive);
}

/**
 * Delete a directory.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function rmdir(string $path): Promise
{
    return filesystem()->deleteDirectory($path);
}

/**
 * Retrieve an array of files and directories inside the specified path.
 *
 * Dot entries are not included in the resulting array (i.e. "." and "..").
 *
 * @param string $path
 *
 * @return Promise<list<string>>
 */
function scandir(string $path): Promise
{
    return filesystem()->listFiles($path);
}

/**
 * Change permissions of a file or directory.
 *
 * @param string $path
 * @param int    $mode
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function chmod(string $path, int $mode): Promise
{
    return filesystem()->changePermissions($path, $mode);
}

/**
 * Change ownership of a file or directory.
 *
 * @param string   $path
 * @param int|null $uid null to ignore
 * @param int|null $gid null to ignore
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function chown(string $path, ?int $uid, ?int $gid = null): Promise
{
    return filesystem()->changeOwner($path, $uid, $gid);
}

/**
 * Update the access and modification time of the specified path.
 *
 * If the file does not exist it will be created automatically.
 *
 * @param string   $path
 * @param int|null $time The touch time. If $time is not supplied, the current system time is used.
 * @param int|null $atime The access time. If $atime is not supplied, value passed to the $time parameter is used.
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return Promise<void>
 */
function touch(string $path, ?int $time = null, ?int $atime = null): Promise
{
    return filesystem()->touch($path, $time, $atime);
}

/**
 * Buffer the specified file's contents.
 *
 * @param string $path The file path from which to buffer contents.
 *
 * @return Promise<string>
 */
function get(string $path): Promise
{
    return filesystem()->read($path);
}

/**
 * Write the contents string to the specified path.
 *
 * @param string $path The file path to which to $contents should be written.
 * @param string $contents The data to write to the specified $path.
 *
 * @return Promise<void>
 */
function put(string $path, string $contents): Promise
{
    return filesystem()->write($path, $contents);
}
