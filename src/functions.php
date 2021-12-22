<?php

namespace Amp\File;

use Amp\File\Driver\BlockingDriver;
use Amp\File\Driver\EioDriver;
use Amp\File\Driver\ParallelDriver;
use Amp\File\Driver\StatusCachingDriver;
use Amp\File\Driver\UvDriver;
use Revolt\EventLoop;

/**
 * Retrieve the application-wide filesystem instance.
 *
 * @param Driver|null $driver Use the specified object as the application-wide filesystem instance.
 *
 * @return Filesystem
 */
function filesystem(?Driver $driver = null): Filesystem
{
    static $map;
    $map ??= new \WeakMap();

    $loop = EventLoop::getDriver();

    if ($driver === null) {
        if (isset($map[$loop])) {
            return $map[$loop];
        }

        $defaultDriver = createDefaultDriver();

        if (!\defined("AMP_WORKER")) { // Prevent caching in workers, cache in parent instead.
            $defaultDriver = new StatusCachingDriver($defaultDriver);
        }

        $filesystem = new Filesystem($defaultDriver);
    } else {
        $filesystem = new Filesystem($driver);
    }

    if (\defined("AMP_WORKER") && $driver instanceof ParallelDriver) {
        throw new \Error("Cannot use the parallel driver within a worker");
    }

    $map[$loop] = $filesystem;

    return $filesystem;
}

/**
 * Create a new filesystem driver best-suited for the current environment.
 *
 * @return Driver
 */
function createDefaultDriver(): Driver
{
    $driver = EventLoop::getDriver();

    if (UvDriver::isSupported($driver)) {
        /** @var EventLoop\Driver\UvDriver $driver */
        return new UvDriver($driver);
    }

    if (EioDriver::isSupported()) {
        return new EioDriver($driver);
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
 * @return File
 *
 * @throws FilesystemException
 */
function openFile(string $path, string $mode): File
{
    return filesystem()->openFile($path, $mode);
}

/**
 * Execute a file stat operation.
 *
 * If the requested path does not exist the function will return NULL.
 *
 * @param string $path File system path.
 *
 * @return array|null
 */
function getStatus(string $path): ?array
{
    return filesystem()->getStatus($path);
}

/**
 * Same as {@see Filesystem::getStatus()} except if the path is a link then the link's data is returned.
 *
 * If the requested path does not exist the function will return NULL.
 *
 * @param string $path File system path.
 *
 * @return array|null
 */
function getLinkStatus(string $path): ?array
{
    return filesystem()->getLinkStatus($path);
}

/**
 * Does the specified path exist?
 *
 * This function should never resolve as a failure -- only a successful bool value
 * indicating the existence of the specified path.
 *
 * @param string $path File system path.
 *
 * @return bool
 */
function exists(string $path): bool
{
    return filesystem()->exists($path);
}

/**
 * Retrieve the size in bytes of the file at the specified path.
 *
 * @param string $path File system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist or is not a file.
 *
 * @return int
 */
function getSize(string $path): int
{
    return filesystem()->getSize($path);
}

/**
 * Does the specified path exist and is it a directory?
 *
 * @param string $path File system path.
 *
 * @return bool
 */
function isDirectory(string $path): bool
{
    return filesystem()->isDirectory($path);
}

/**
 * Does the specified path exist and is it a file?
 *
 * @param string $path File system path.
 *
 * @return bool
 */
function isFile(string $path): bool
{
    return filesystem()->isFile($path);
}

/**
 * Does the specified path exist and is it a symlink?
 *
 * @param string $path File system path.
 *
 * @return bool
 */
function isSymlink(string $path): bool
{
    return filesystem()->isSymlink($path);
}

/**
 * Retrieve the path's last modification time as a unix timestamp.
 *
 * @param string $path File system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 *
 * @return int
 */
function getModificationTime(string $path): int
{
    return filesystem()->getModificationTime($path);
}

/**
 * Retrieve the path's last access time as a unix timestamp.
 *
 * @param string $path File system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 *
 * @return int
 */
function getAccessTime(string $path): int
{
    return filesystem()->getAccessTime($path);
}

/**
 * Retrieve the path's creation time as a unix timestamp.
 *
 * @param string $path File system path.
 * @fails \Amp\Files\FilesystemException If the path does not exist.
 */
function getCreationTime(string $path): int
{
    return filesystem()->getCreationTime($path);
}

/**
 * Create a symlink $link pointing to the file/directory located at $original.
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function createSymlink(string $original, string $link): void
{
    filesystem()->createSymlink($original, $link);
}

/**
 * Create a hard link $link pointing to the file/directory located at $target.
 *
 * @param string $target
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function createHardlink(string $target, string $link): void
{
    filesystem()->createHardlink($target, $link);
}

/**
 * Resolve the symlink at $path.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 *
 * @return string
 */
function resolveSymlink(string $path): string
{
    return filesystem()->resolveSymlink($path);
}

/**
 * Move / rename a file or directory.
 *
 * @param string $from
 * @param string $to
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function move(string $from, string $to): void
{
    filesystem()->move($from, $to);
}

/**
 * Delete a file.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function deleteFile(string $path): void
{
    filesystem()->deleteFile($path);
}

/**
 * Create a directory.
 *
 * @param string $path
 * @param int    $mode
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function createDirectory(string $path, int $mode = 0777): void
{
    filesystem()->createDirectory($path, $mode);
}

/**
 * Create a directory recursively.
 *
 * @param string $path
 * @param int    $mode
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function createDirectoryRecursively(string $path, int $mode = 0777): void
{
    filesystem()->createDirectoryRecursively($path, $mode);
}

/**
 * Delete a directory.
 *
 * @param string $path
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function deleteDirectory(string $path): void
{
    filesystem()->deleteDirectory($path);
}

/**
 * Retrieve an array of files and directories inside the specified path.
 *
 * Dot entries are not included in the resulting array (i.e. "." and "..").
 *
 * @param string $path
 *
 * @returnlist<string>
 */
function listFiles(string $path): array
{
    return filesystem()->listFiles($path);
}

/**
 * Change permissions of a file or directory.
 *
 * @param string $path
 * @param int    $mode
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function changePermissions(string $path, int $mode): void
{
    filesystem()->changePermissions($path, $mode);
}

/**
 * Change ownership of a file or directory.
 *
 * @param string   $path
 * @param int|null $uid null to ignore
 * @param int|null $gid null to ignore
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function changeOwner(string $path, ?int $uid, ?int $gid = null): void
{
    filesystem()->changeOwner($path, $uid, $gid);
}

/**
 * Update the access and modification time of the specified path.
 *
 * If the file does not exist it will be created automatically.
 *
 * @param string   $path
 * @param int|null $modificationTime The touch time. If $time is not supplied, the current system time is used.
 * @param int|null $accessTime The access time. If not supplied, the modification time is used.
 * @fails \Amp\Files\FilesystemException If the operation fails.
 */
function touch(string $path, ?int $modificationTime = null, ?int $accessTime = null): void
{
    filesystem()->touch($path, $modificationTime, $accessTime);
}

/**
 * Buffer the specified file's contents.
 *
 * @param string $path The file path from which to buffer contents.
 *
 * @return string
 */
function read(string $path): string
{
    return filesystem()->read($path);
}

/**
 * Write the contents string to the specified path.
 *
 * @param string $path The file path to which to $contents should be written.
 * @param string $contents The data to write to the specified $path.
 */
function write(string $path, string $contents): void
{
    filesystem()->write($path, $contents);
}
