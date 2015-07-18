<?php

namespace Amp\Fs;

interface Filesystem {
    const READ   = 0b001;
    const WRITE  = 0b010;
    const CREATE = 0b100;

    /**
     * Open a file handle for reading and/or writing
     *
     * At least READ or WRITE is required in the mode bitmask. If the file does not exist the
     * CREATE flag is necessary in READ mode or the operation will fail. When WRITE mode is
     * specified in the bitmask the file will always be created if it does not already exist.
     *
     * Example:
     *
     *     <?php
     *     use function Amp\Fs\fs();
     *     use Amp\Fs\{ Filesystem, Descriptor };
     *
     *     function() {
     *         $fs = fs();
     *         $mode = Filesystem::READ | Filesystem::WRITE;
     *         $fh = yield $fs->open("/path/to/file", $mode);
     *         assert($fh instanceof Descriptor);
     *
     *
     * NOTE: This operation is only valid for files (not directories).
     *
     * @param string $path The filesystem path to open
     * @param int $mode A flag bitmask: [Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE]
     * @return \Amp\Promise
     */
    public function open($path, $mode = self::READ);

    /**
     * Execute a file stat operation
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path The file system path to stat
     * @return \Amp\Promise A promise resolving to an associative array upon successful resolution
     */
    public function stat($path);

    /**
     * Same as stat() except if the path is a link then the link's data is returned
     *
     * @param string $path The file system path to stat
     * @return \Amp\Promise A promise resolving to an associative array upon successful resolution
     */
    public function lstat($path);

    /**
     * Create a symlink $link pointing to the file/directory located at $target
     *
     * @param string $target
     * @param string $link
     * @return \Amp\Promise
     */
    public function symlink($target, $link);

    /**
     * Rename a file or directory
     *
     * @param string $from
     * @param string $to
     * @return \Amp\Promise
     */
    public function rename($from, $to);

    /**
     * Delete a file
     *
     * @param string $path
     * @return \Amp\Promise
     */
    public function unlink($path);

    /**
     * Create a director
     *
     * @param string $path
     * @param int $mode
     * @return \Amp\Promise
     */
    public function mkdir($path, $mode = 0644);

    /**
     * Delete a directory
     *
     * @param string $path
     * @return \Amp\Promise
     */
    public function rmdir($path);

    /**
     * Retrieve an array of files and directories inside the specified path
     *
     * Dot entries are not included in the resulting array (i.e. "." and "..").
     *
     * @param string $path
     * @return \Amp\Promise
     */
    public function scandir($path);

    /**
     * chmod a file or directory
     *
     * @param string $path
     * @param int $mode
     * @return \Amp\Promise
     */
    public function chmod($path, $mode);

    /**
     * chown a file or directory
     *
     * @param string $path
     * @param int $uid
     * @param int $gid
     * @return \Amp\Promise
     */
    public function chown($path, $uid, $gid);

    /**
     * Update the access and modification time of the specified path
     *
     * If the file does not exist it will be created automatically.
     *
     * @param string $path
     * @return \Amp\Promise
     */
    public function touch($path);

    /**
     * Buffer the specified file's contents
     *
     * @param string $path The file path from which to buffer contents
     * @return \Amp\Promise A promise resolving to a string upon successful resolution
     */
    public function get($path);

    /**
     * Write the contents string to the specified path.
     *
     * @param string $path The file path to which to $contents should be written
     * @param string $contents The data to write to the specified $path
     * @return \Amp\Promise A promise resolving to the integer length written upon success
     */
    public function put($path, $contents);
}
