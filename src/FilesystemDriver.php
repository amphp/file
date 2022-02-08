<?php

namespace Amp\File;

interface FilesystemDriver
{
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
    public function openFile(string $path, string $mode): File;

    /**
     * Get file status; also known as stat operation.
     *
     * If the requested path does not exist, it returns {@code null}.
     *
     * @param string $path The file system path to stat.
     *
     * @return array|null
     */
    public function getStatus(string $path): ?array;

    /**
     * Same as {@see FilesystemDriver::getStatus()} except if the path is a link then the link's data is returned.
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path The file system path to stat.
     *
     * @return array|null An associative array upon successful completion.
     */
    public function getLinkStatus(string $path): ?array;

    /**
     * Create a symlink $link pointing to the file/directory located at $target.
     *
     * @param string $target
     * @param string $link
     *
     * @throws FilesystemException
     */
    public function createSymlink(string $target, string $link): void;

    /**
     * Create a hard link $link pointing to the file/directory located at $target.
     *
     * @param string $target
     * @param string $link
     *
     * @throws FilesystemException
     */
    public function createHardlink(string $target, string $link): void;

    /**
     * Resolve the symlink at $path.
     *
     * @param string $target
     *
     * @throws FilesystemException
     */
    public function resolveSymlink(string $target): string;

    /**
     * Move / rename a file or directory.
     *
     * @param string $from
     * @param string $to
     *
     * @throws FilesystemException
     */
    public function move(string $from, string $to): void;

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @throws FilesystemException
     */
    public function deleteFile(string $path): void;

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     *
     * @throws FilesystemException
     */
    public function createDirectory(string $path, int $mode = 0777): void;

    /**
     * Create a directory recursively.
     *
     * @param string $path
     * @param int $mode
     *
     * @throws FilesystemException If the operation fails.
     */
    public function createDirectoryRecursively(string $path, int $mode = 0777): void;

    /**
     * Delete a directory.
     *
     * @param string $path
     *
     * @throws FilesystemException If the operation fails.
     */
    public function deleteDirectory(string $path): void;

    /**
     * Retrieve an array of files and directories inside the specified path.
     *
     * Dot entries are not included in the resulting array (i.e. "." and "..").
     *
     * @param string $path
     *
     * @return list<string>
     *
     * @throws FilesystemException If the operation fails.
     */
    public function listFiles(string $path): array;

    /**
     * chmod a file or directory.
     *
     * @param string $path
     * @param int $mode
     *
     * @throws FilesystemException If the operation fails.
     */
    public function changePermissions(string $path, int $mode): void;

    /**
     * chown a file or directory.
     *
     * @param string $path
     * @param int|null $uid
     * @param int|null $gid
     *
     * @throws FilesystemException If the operation fails.
     */
    public function changeOwner(string $path, ?int $uid, ?int $gid): void;

    /**
     * Update the access and modification time of the specified path.
     *
     * If the file does not exist it will be created automatically.
     *
     * @param string $path
     * @param int|null $modificationTime The touch time. If $time is not supplied, the current system time is used.
     * @param int|null $accessTime The access time. If $atime is not supplied, value passed to the $time parameter is
     *     used.
     *
     * @throws FilesystemException If the operation fails.
     */
    public function touch(string $path, ?int $modificationTime, ?int $accessTime): void;

    /**
     * Buffer the specified file's contents.
     *
     * @param string $path The file path from which to buffer contents.
     *
     * @return string A promise resolving to a string upon successful resolution.
     *
     * @throws FilesystemException If the operation fails.
     */
    public function read(string $path): string;

    /**
     * Write the contents string to the specified path.
     *
     * @param string $path The file path to which to $contents should be written.
     * @param string $contents The data to write to the specified $path.
     *
     * @throws FilesystemException If the operation fails.
     */
    public function write(string $path, string $contents): void;
}
