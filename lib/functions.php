<?php declare(strict_types = 1);

namespace Amp\File;

use Amp\Parallel\Worker\Worker;
use Interop\Async\{ Awaitable, Loop };

const LOOP_STATE_IDENTIFIER = Driver::class;

/**
 * Retrieve the application-wide filesystem instance
 *
 * @param \Amp\File\Driver $driver Use the specified object as the application-wide filesystem instance
 * @return \Amp\File\Driver
 */
function filesystem(Driver $driver = null): Driver {
    if ($driver === null) {
        $driver = Loop::getState(LOOP_STATE_IDENTIFIER);
        if ($driver) {
            return $driver;
        }
        
        $driver = driver();
    }
    Loop::setState(LOOP_STATE_IDENTIFIER, $driver);
    return $driver;
}

/**
 * Create a new filesystem driver best-suited for the current environment
 *
 * @return \Amp\File\Driver
 */
function driver(): Driver {
    $driver = Loop::get();
    $loop = $driver->getHandle();
    if (\is_resource($loop) && \get_resource_type($loop) == "uv_loop") {
        return new UvDriver($driver);
    } elseif (\extension_loaded("eio")) {
        return new EioDriver;
    } elseif (\interface_exists(Worker::class)) {
        return new ParallelDriver;
    } else {
        return new BlockingDriver;
    }
}

/**
 * Open a handle for the specified path
 *
 * @param string $path
 * @param string $mode
 * @return \Interop\Async\Awaitable<\Amp\File\Handle>
 */
function open(string $path, string $mode): Awaitable {
    return filesystem()->open($path, $mode);
}

/**
 * Execute a file stat operation
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise whould never resolve as a failure.
 *
 * @param string $path An absolute file system path
 * @return \Interop\Async\Awaitable<array|null>
 */
function stat(string $path): Awaitable {
    return filesystem()->stat($path);
}

/**
 * Does the specified path exist?
 *
 * This function should never resolve as a failure -- only a successfull bool value
 * indicating the existence of the specified path.
 *
 * @param string $path An absolute file system path
 * @return \Interop\Async\Awaitable<bool>
 */
function exists(string $path): Awaitable {
    return filesystem()->exists($path);
}

/**
 * Retrieve the size in bytes of the file at the specified path.
 *
 * If the path does not exist or is not a regular file this
 * function's returned Promise WILL resolve as a failure.
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist or is not a file
 * @return \Interop\Async\Awaitable<int>
 */
function size(string $path): Awaitable {
    return filesystem()->size($path);
}

/**
 * Does the specified path exist and is it a directory?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path
 * @return \Interop\Async\Awaitable<bool>
 */
function isdir(string $path): Awaitable {
    return filesystem()->isdir($path);
}

/**
 * Does the specified path exist and is it a file?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path
 * @return \Interop\Async\Awaitable<bool>
 */
function isfile(string $path): Awaitable {
    return filesystem()->isfile($path);
}

/**
 * Retrieve the path's last modification time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Interop\Async\Awaitable<int>
 */
function mtime(string $path): Awaitable {
    return filesystem()->mtime($path);
}

/**
 * Retrieve the path's last access time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Interop\Async\Awaitable<int>
 */
function atime($path) {
    return filesystem()->atime($path);
}

/**
 * Retrieve the path's creation time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Interop\Async\Awaitable<int>
 */
function ctime(string $path): Awaitable {
    return filesystem()->ctime($path);
}

/**
 * Same as stat() except if the path is a link then the link's data is returned
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise whould never resolve as a failure.
 *
 * @param string $path An absolute file system path
 * @return \Interop\Async\Awaitable<array|null>
 */
function lstat(string $path): Awaitable {
    return filesystem()->lstat($path);
}

/**
 * Create a symlink $link pointing to the file/directory located at $original
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Interop\Async\Awaitable<null>
 */
function symlink(string $original, string $link): Awaitable {
    return filesystem()->symlink($original, $link);
}

/**
 * Create a hard link $link pointing to the file/directory located at $original
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Interop\Async\Awaitable<null>
 */
function link(string $original, string $link): Awaitable {
    return filesystem()->symlink($original, $link);
}

/**
 * Read the symlink at $path
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Interop\Async\Awaitable<null>
 */
function readlink(string $path): Awaitable {
    return filesystem()->readlink($path);
}

/**
 * Rename a file or directory
 *
 * @param string $from
 * @param string $to
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Interop\Async\Awaitable<null>
 */
function rename(string $from, string $to): Awaitable {
    return filesystem()->rename($from, $to);
}

/**
 * Delete a file
 *
 * @param string $path
 * @return \Interop\Async\Awaitable<null>
 */
function unlink(string $path): Awaitable {
    return filesystem()->unlink($path);
}

/**
 * Create a director
 *
 * @param string $path
 * @param int $mode
 * @return \Interop\Async\Awaitable<null>
 */
function mkdir(string $path, int $mode = 0644): Awaitable {
    return filesystem()->mkdir($path, $mode);
}

/**
 * Delete a directory
 *
 * @param string $path
 * @return \Interop\Async\Awaitable<null>
 */
function rmdir(string $path): Awaitable {
    return filesystem()->rmdir($path);
}

/**
 * Retrieve an array of files and directories inside the specified path
 *
 * Dot entries are not included in the resulting array (i.e. "." and "..").
 *
 * @param string $path
 * @return \Interop\Async\Awaitable<array>
 */
function scandir(string $path): Awaitable {
    return filesystem()->scandir($path);
}

/**
 * chmod a file or directory
 *
 * @param string $path
 * @param int $mode
 * @return \Interop\Async\Awaitable<null>
 */
function chmod(string $path, int $mode): Awaitable {
    return filesystem()->chmod($path, $mode);
}

/**
 * chown a file or directory
 *
 * @param string $path
 * @param int $uid -1 to ignore
 * @param int $gid -1 to ignore
 * @return \Interop\Async\Awaitable<null>
 */
function chown(string $path, int $uid, int $gid = -1): Awaitable {
    return filesystem()->chown($path, $uid, $gid);
}

/**
 * Update the access and modification time of the specified path
 *
 * If the file does not exist it will be created automatically.
 *
 * @param string $path
 * @return \Interop\Async\Awaitable<null>
 */
function touch(string $path): Awaitable {
    return filesystem()->touch($path);
}

/**
 * Buffer the specified file's contents
 *
 * @param string $path The file path from which to buffer contents
 * @return \Interop\Async\Awaitable<string>
 */
function get(string $path): Awaitable {
    return filesystem()->get($path);
}

/**
 * Write the contents string to the specified path.
 *
 * @param string $path The file path to which to $contents should be written
 * @param string $contents The data to write to the specified $path
 * @return \Interop\Async\Awaitable A promise resolving to the integer length written upon success
 */
function put(string $path, string $contents): Awaitable {
    return filesystem()->put($path, $contents);
}
