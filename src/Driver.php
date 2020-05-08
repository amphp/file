<?php

namespace Amp\File;

use Amp\Promise;

interface Driver
{
    /**
     * Open a handle for the specified path.
     *
     * @param string $path
     * @param string $mode
     * @return Promise<File>
     */
    public function open(string $path, string $mode): Promise;

    /**
     * Execute a file stat operation.
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path The file system path to stat
     * @return Promise<array|null>
     */
    public function stat(string $path): Promise;

    /**
     * Same as stat() except if the path is a link then the link's data is returned.
     *
     * @param string $path The file system path to stat
     * @return Promise<array|null> A promise resolving to an associative array upon successful resolution
     */
    public function lstat(string $path): Promise;

    /**
     * Create a symlink $link pointing to the file/directory located at $target.
     *
     * @param string $target
     * @param string $link
     * @return Promise<void>
     */
    public function symlink(string $target, string $link): Promise;

    /**
     * Create a hard link $link pointing to the file/directory located at $target.
     *
     * @param string $target
     * @param string $link
     * @return Promise<void>
     */
    public function link(string $target, string $link): Promise;

    /**
     * Read the symlink at $path.
     *
     * @param string $target
     * @return Promise<string>
     */
    public function readlink(string $target): Promise;

    /**
     * Rename a file or directory.
     *
     * @param string $from
     * @param string $to
     * @return Promise<void>
     */
    public function rename(string $from, string $to): Promise;

    /**
     * Delete a file.
     *
     * @param string $path
     * @return Promise<void>
     */
    public function unlink(string $path): Promise;

    /**
     * Create a director.
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @return Promise<void>
     */
    public function mkdir(string $path, int $mode = 0777, bool $recursive = false): Promise;

    /**
     * Delete a directory.
     *
     * @param string $path
     * @return Promise<void>
     */
    public function rmdir(string $path): Promise;

    /**
     * Retrieve an array of files and directories inside the specified path.
     *
     * Dot entries are not included in the resulting array (i.e. "." and "..").
     *
     * @param string $path
     * @return Promise<list<string>>
     */
    public function scandir(string $path): Promise;

    /**
     * chmod a file or directory.
     *
     * @param string $path
     * @param int $mode
     * @return Promise<void>
     */
    public function chmod(string $path, int $mode): Promise;

    /**
     * chown a file or directory.
     *
     * @param string $path
     * @param int $uid
     * @param int $gid
     * @return Promise<void>
     */
    public function chown(string $path, int $uid, int $gid): Promise;

    /**
     * Update the access and modification time of the specified path.
     *
     * If the file does not exist it will be created automatically.
     *
     * @param string $path
     * @param int|null $time The touch time. If $time is not supplied, the current system time is used.
     * @param int|null $atime The access time. If $atime is not supplied, value passed to the $time parameter is used.
     * @return Promise<void>
     */
    public function touch(string $path, ?int $time = null, ?int $atime = null): Promise;

    /**
     * Buffer the specified file's contents.
     *
     * @param string $path The file path from which to buffer contents
     * @return Promise<string> A promise resolving to a string upon successful resolution
     */
    public function get(string $path): Promise;

    /**
     * Write the contents string to the specified path.
     *
     * @param string $path The file path to which to $contents should be written
     * @param string $contents The data to write to the specified $path
     * @return Promise<void>
     */
    public function put(string $path, string $contents): Promise;
}
