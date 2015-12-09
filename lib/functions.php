<?php

namespace Amp\File;

/**
 * Retrieve the application-wide filesystem instance
 *
 * @param \Amp\File\Driver $assign Use the specified object as the application-wide filesystem instance
 * @return \Amp\File\Driver
 */
function filesystem(Driver $assign = null) {
    static $driver;
    if ($assign) {
        return ($driver = $assign);
    } elseif ($driver) {
        return $driver;
    } else {
        return ($driver = driver());
    }
}

/**
 * Create a new filesystem driver best-suited for the current environment
 *
 * @return \Amp\File\Driver
 */
function driver() {
    $reactor = \Amp\reactor();
    if ($reactor instanceof \Amp\UvReactor) {
        return new UvDriver($reactor);
    } elseif (\extension_loaded("eio")) {
        return new EioDriver;
    } else {
        return new BlockingDriver;
    }
}

/**
 * Open a handle for the specified path
 *
 * @param string $path
 * @param string $mode
 * @return \Amp\File\Handle
 */
function open($path, $mode) {
    return filesystem()->open($path, $mode);
}

/**
 * Execute a file stat operation
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise whould never resolve as a failure.
 *
 * @param string $path An absolute file system path
 * @return \Amp\Promise<array|null>
 */
function stat($path) {
    return filesystem()->stat($path);
}

/**
 * Does the specified path exist?
 *
 * This function should never resolve as a failure -- only a successfull bool value
 * indicating the existence of the specified path.
 *
 * @param string $path An absolute file system path
 * @return \Amp\Promise<bool>
 */
function exists($path) {
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
 * @return \Amp\Promise<int>
 */
function size($path) {
    return filesystem()->size($path);
}

/**
 * Does the specified path exist and is it a directory?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path
 * @return \Amp\Promise<bool>
 */
function isdir($path) {
    return filesystem()->isdir($path);
}

/**
 * Does the specified path exist and is it a file?
 *
 * If the path does not exist the returned Promise will resolve
 * to FALSE and will not reject with an error.
 *
 * @param string $path An absolute file system path
 * @return \Amp\Promise<bool>
 */
function isfile($path) {
    return filesystem()->isfile($path);
}

/**
 * Retrieve the path's last modification time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Amp\Promise<int>
 */
function mtime($path) {
    return filesystem()->mtime($path);
}

/**
 * Retrieve the path's last access time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Amp\Promise<int>
 */
function atime($path) {
    return filesystem()->atime($path);
}

/**
 * Retrieve the path's creation time as a unix timestamp
 *
 * @param string $path An absolute file system path
 * @fails \Amp\Files\FilesystemException If the path does not exist
 * @return \Amp\Promise<int>
 */
function ctime($path) {
    return filesystem()->ctime($path);
}

/**
 * Same as stat() except if the path is a link then the link's data is returned
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 * The returned Promise whould never resolve as a failure.
 *
 * @param string $path An absolute file system path
 * @return \Amp\Promise<array|null>
 */
function lstat($path) {
    return filesystem()->lstat($path);
}

/**
 * Create a symlink $link pointing to the file/directory located at $original
 *
 * @param string $original
 * @param string $link
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Amp\Promise<null>
 */
function symlink($original, $link) {
    return filesystem()->symlink($original, $link);
}

/**
 * Rename a file or directory
 *
 * @param string $from
 * @param string $to
 * @fails \Amp\Files\FilesystemException If the operation fails
 * @return \Amp\Promise<null>
 */
function rename($from, $to) {
    return filesystem()->rename($from, $to);
}

/**
 * Delete a file
 *
 * @param string $path
 * @return \Amp\Promise<null>
 */
function unlink($path) {
    return filesystem()->unlink($path);
}

/**
 * Create a director
 *
 * @param string $path
 * @param int $mode
 * @return \Amp\Promise<null>
 */
function mkdir($path, $mode = 0644) {
    return filesystem()->mkdir($path, $mode);
}

/**
 * Delete a directory
 *
 * @param string $pat
 * @return \Amp\Promise<null>
 */
function rmdir($path) {
    return filesystem()->rmdir($path);
}

/**
 * Retrieve an array of files and directories inside the specified path
 *
 * Dot entries are not included in the resulting array (i.e. "." and "..").
 *
 * @param string $path
 * @return \Amp\Promise<array>
 */
function scandir($path) {
    return filesystem()->scandir($path);
}

/**
 * chmod a file or directory
 *
 * @param string $path
 * @param int $mode
 * @return \Amp\Promise<null>
 */
function chmod($path, $mode) {
    return filesystem()->chmod($path, $mode);
}

/**
 * chown a file or directory
 *
 * @param string $path
 * @param int $uid -1 to ignore
 * @param int $gid -1 to ignore
 * @return \Amp\Promise<null>
 */
function chown($path, $uid, $gid = -1) {
    return filesystem()->chown($path, $uid, $gid);
}

/**
 * Update the access and modification time of the specified path
 *
 * If the file does not exist it will be created automatically.
 *
 * @param string $path
 * @return \Amp\Promise<null>
 */
function touch($path) {
    return filesystem()->touch($path);
}

/**
 * Buffer the specified file's contents
 *
 * @param string $path The file path from which to buffer contents
 * @return \Amp\Promise<string>
 */
function get($path) {
    return filesystem()->get($path);
}

/**
 * Write the contents string to the specified path.
 *
 * @param string $path The file path to which to $contents should be written
 * @param string $contents The data to write to the specified $path
 * @return \Amp\Promise A promise resolving to the integer length written upon success
 */
function put($path, $contents) {
    return filesystem()->put($path, $contents);
}
