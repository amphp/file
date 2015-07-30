<?php

namespace Amp\Fs;

/**
 * Retrieve the application-wide filesystem instance
 *
 * @param \Amp\Fs\Filesystem $assign Use the specified object as the application-wide filesystem instance
 * @return \Amp\Fs\Filesystem
 */
function filesystem(Filesystem $assign = null) {
    static $filesystem;
    if ($assign) {
        return ($filesystem = $assign);
    } elseif ($filesystem) {
        return $filesystem;
    } else {
        return ($filesystem = init());
    }
}

/**
 * Create a new filesystem instance best-suited for the current environment
 *
 * @return \Amp\Fs\Filesystem
 */
function init() {
    if (\extension_loaded("uv")) {
        return new UvFilesystem(\Amp\reactor());
    } elseif (\extension_loaded("eio")) {
        return new EioFilesystem;
    } else {
        return BlockingFilesystem;
    }
}

/**
 * Execute a file stat operation
 *
 * If the requested path does not exist the resulting Promise will resolve to NULL.
 *
 * @param string $path The file system path to stat
 * @return \Amp\Promise A promise resolving to an associative array upon successful resolution
 */
function stat($path) {
    return filesystem()->stat($path);
}

/**
 * Same as stat() except if the path is a link then the link's data is returned
 *
 * @param string $path The file system path to stat
 * @return \Amp\Promise A promise resolving to an associative array upon successful resolution
 */
function lstat($path) {
    return filesystem()->lstat($path);
}

/**
 * Create a symlink $link pointing to the file/directory located at $target
 *
 * @param string $target
 * @param string $link
 * @return \Amp\Promise
 */
function symlink($target, $link) {
    return filesystem()->symlink($target, $link);
}

/**
 * Rename a file or directory
 *
 * @param string $from
 * @param string $to
 * @return \Amp\Promise
 */
function rename($from, $to) {
    return filesystem()->rename($from, $to);
}

/**
 * Delete a file
 *
 * @param string $path
 * @return \Amp\Promise
 */
function unlink($path) {
    return filesystem()->unlink($path);
}

/**
 * Create a director
 *
 * @param string $path
 * @param int $mode
 * @return \Amp\Promise
 */
function mkdir($path, $mode = 0644) {
    return filesystem()->mkdir($path, $mode);
}

/**
 * Delete a directory
 *
 * @param string $path
 * @return \Amp\Promise
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
 * @return \Amp\Promise
 */
function scandir($path) {
    return filesystem()->scandir($path);
}

/**
 * chmod a file or directory
 *
 * @param string $path
 * @param int $mode
 * @return \Amp\Promise
 */
function chmod($path, $mode) {
    return filesystem()->chmod($path, $mode);
}

/**
 * chown a file or directory
 *
 * @param string $path
 * @param int $uid
 * @param int $gid
 * @return \Amp\Promise
 */
function chown($path, $uid, $gid) {
    return filesystem()->chown($path, $uid, $gid);
}

/**
 * Update the access and modification time of the specified path
 *
 * If the file does not exist it will be created automatically.
 *
 * @param string $path
 * @return \Amp\Promise
 */
function touch($path) {
    return filesystem()->touch($path);
}

/**
 * Buffer the specified file's contents
 *
 * @param string $path The file path from which to buffer contents
 * @return \Amp\Promise A promise resolving to a string upon successful resolution
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
