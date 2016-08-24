<?php declare(strict_types = 1);

namespace Amp\File;

use Interop\Async\Awaitable;

interface Driver {
    /**
     * Open a handle for the specified path
     *
     * @param string $path
     * @param string $mode
     * @return \Interop\Async\Awaitable<\Amp\File\Handle>
     */
    public function open(string $path, string $mode): Awaitable;

    /**
     * Execute a file stat operation
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path The file system path to stat
     * @return \Interop\Async\Awaitable<array|null>
     */
    public function stat(string $path): Awaitable;

    /**
     * Does the specified path exist?
     *
     * This function should never resolve as a failure -- only a successfull bool value
     * indicating the existence of the specified path.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<bool>
     */
    public function exists(string $path): Awaitable;

    /**
     * Retrieve the size in bytes of the file at the specified path.
     *
     * If the path does not exist or is not a regular file this
     * function's returned Promise WILL resolve as a failure.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function size(string $path): Awaitable;

    /**
     * Does the specified path exist and is it a directory?
     *
     * If the path does not exist the returned Promise will resolve
     * to FALSE and will not reject with an error.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<bool>
     */
    public function isdir(string $path): Awaitable;

    /**
     * Does the specified path exist and is it a file?
     *
     * If the path does not exist the returned Promise will resolve
     * to FALSE and will not reject with an error.
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<bool>
     */
    public function isfile(string $path): Awaitable;

    /**
     * Retrieve the path's last modification time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function mtime(string $path): Awaitable;

    /**
     * Retrieve the path's last access time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function atime(string $path): Awaitable;

    /**
     * Retrieve the path's creation time as a unix timestamp
     *
     * @param string $path An absolute file system path
     * @return \Interop\Async\Awaitable<int>
     */
    public function ctime(string $path): Awaitable;

    /**
     * Same as stat() except if the path is a link then the link's data is returned
     *
     * @param string $path The file system path to stat
     * @return \Interop\Async\Awaitable A promise resolving to an associative array upon successful resolution
     */
    public function lstat(string $path): Awaitable;

    /**
     * Create a symlink $link pointing to the file/directory located at $target
     *
     * @param string $target
     * @param string $link
     * @return \Interop\Async\Awaitable
     */
    public function symlink(string $target, string $link): Awaitable;
    
    /**
     * Create a hard link $link pointing to the file/directory located at $target
     *
     * @param string $target
     * @param string $link
     * @return \Interop\Async\Awaitable
     */
    public function link(string $target, string $link): Awaitable;
    
    /**
     * Read the symlink at $path.
     *
     * @param string $target
     * @return \Interop\Async\Awaitable
     */
    public function readlink(string $target): Awaitable;
    
    /**
     * Rename a file or directory
     *
     * @param string $from
     * @param string $to
     * @return \Interop\Async\Awaitable
     */
    public function rename(string $from, string $to): Awaitable;

    /**
     * Delete a file
     *
     * @param string $path
     * @return \Interop\Async\Awaitable
     */
    public function unlink(string $path): Awaitable;

    /**
     * Create a director
     *
     * @param string $path
     * @param int $mode
     * @return \Interop\Async\Awaitable
     */
    public function mkdir(string $path, int $mode = 0644): Awaitable;

    /**
     * Delete a directory
     *
     * @param string $path
     * @return \Interop\Async\Awaitable
     */
    public function rmdir(string $path): Awaitable;

    /**
     * Retrieve an array of files and directories inside the specified path
     *
     * Dot entries are not included in the resulting array (i.e. "." and "..").
     *
     * @param string $path
     * @return \Interop\Async\Awaitable
     */
    public function scandir(string $path): Awaitable;

    /**
     * chmod a file or directory
     *
     * @param string $path
     * @param int $mode
     * @return \Interop\Async\Awaitable
     */
    public function chmod(string $path, int $mode): Awaitable;

    /**
     * chown a file or directory
     *
     * @param string $path
     * @param int $uid
     * @param int $gid
     * @return \Interop\Async\Awaitable
     */
    public function chown(string $path, int $uid, int $gid): Awaitable;

    /**
     * Update the access and modification time of the specified path
     *
     * If the file does not exist it will be created automatically.
     *
     * @param string $path
     * @return \Interop\Async\Awaitable
     */
    public function touch(string $path): Awaitable;

    /**
     * Buffer the specified file's contents
     *
     * @param string $path The file path from which to buffer contents
     * @return \Interop\Async\Awaitable A promise resolving to a string upon successful resolution
     */
    public function get(string $path): Awaitable;

    /**
     * Write the contents string to the specified path.
     *
     * @param string $path The file path to which to $contents should be written
     * @param string $contents The data to write to the specified $path
     * @return \Interop\Async\Awaitable A promise resolving to the integer length written upon success
     */
    public function put(string $path, string $contents): Awaitable;
}
