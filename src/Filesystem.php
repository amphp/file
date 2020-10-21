<?php

namespace Amp\File;

use Amp\Promise;
use function Amp\call;

final class Filesystem
{
    /** @var Driver */
    private $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Open a handle for the specified path.
     *
     * @param string $path
     * @param string $mode
     *
     * @return Promise<File>
     */
    public function openFile(string $path, string $mode): Promise
    {
        return $this->driver->openFile($path, $mode);
    }

    /**
     * Execute a file stat operation.
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path File system path.
     *
     * @return Promise<array|null>
     */
    public function getStatus(string $path): Promise
    {
        return $this->driver->getStatus($path);
    }

    /**
     * Same as {@see Filesystem::getStatus()} except if the path is a link then the link's data is returned.
     *
     * If the requested path does not exist the resulting Promise will resolve to NULL.
     *
     * @param string $path File system path.
     *
     * @return Promise<array|null>
     */
    public function getLinkStatus(string $path): Promise
    {
        return $this->driver->getLinkStatus($path);
    }

    /**
     * Does the specified path exist?
     *
     * This function should never resolve as a failure -- only a successful bool value
     * indicating the existence of the specified path.
     *
     * @param string $path File system path.
     *
     * @return Promise<bool>
     */
    public function exists(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);

            return $result !== null;
        });
    }

    /**
     * Retrieve the size in bytes of the file at the specified path.
     *
     * If the path does not exist or is not a regular file this
     * function's returned Promise WILL resolve as a failure.
     *
     * @param string $path File system path.
     * @fails \Amp\Files\FilesystemException If the path does not exist or is not a file.
     *
     * @return Promise<int>
     */
    public function getSize(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                throw new FilesystemException("Failed to read file size for '{$path}'");
            }

            if ($result['mode'] & 0100000) {
                return $result["size"];
            }

            throw new FilesystemException("Failed to read file size for '{$path}'; specified path is not a regular file");
        });
    }

    /**
     * Does the specified path exist and is it a directory?
     *
     * @param string $path File system path.
     *
     * @return Promise<bool>
     */
    public function isDirectory(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                return false;
            }

            return (bool) ($result['mode'] & 0040000);
        });
    }

    /**
     * Does the specified path exist and is it a file?
     *
     * @param string $path File system path.
     *
     * @return Promise<bool>
     */
    public function isFile(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                return false;
            }

            return (bool) ($result['mode'] & 0100000);
        });
    }

    /**
     * Does the specified path exist and is it a symlink?
     *
     * If the path does not exist the returned Promise will resolve
     * to FALSE and will not reject with an error.
     *
     * @param string $path File system path.
     *
     * @return Promise<bool>
     */
    public function isSymlink(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getLinkStatus($path);
            if ($result === null) {
                return false;
            }

            return ($result['mode'] & 0120000) === 0120000;
        });
    }

    /**
     * Retrieve the path's last modification time as a unix timestamp.
     *
     * @param string $path File system path.
     * @fails \Amp\Files\FilesystemException If the path does not exist.
     *
     * @return Promise<int>
     */
    public function getModificationTime(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                throw new FilesystemException("Failed to read file modification time for '{$path}'");
            }

            return $result["mtime"];
        });
    }

    /**
     * Retrieve the path's last access time as a unix timestamp.
     *
     * @param string $path File system path.
     * @fails \Amp\Files\FilesystemException If the path does not exist.
     *
     * @return Promise<int>
     */
    public function getAccessTime(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                throw new FilesystemException("Failed to read file access time for '{$path}'");
            }

            return $result["atime"];
        });
    }

    /**
     * Retrieve the path's creation time as a unix timestamp.
     *
     * @param string $path File system path.
     * @fails \Amp\Files\FilesystemException If the path does not exist.
     *
     * @return Promise<int>
     */
    public function getCreationTime(string $path): Promise
    {
        return call(function () use ($path) {
            $result = yield $this->getStatus($path);
            if ($result === null) {
                throw new FilesystemException("Failed to read file creation time for '{$path}'");
            }

            return $result["ctime"];
        });
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
    public function createSymlink(string $original, string $link): Promise
    {
        return $this->driver->createSymlink($original, $link);
    }

    /**
     * Create a hard link $link pointing to the file/directory located at $target.
     *
     * @param string $target
     * @param string $link
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function createHardlink(string $target, string $link): Promise
    {
        return $this->driver->createHardlink($target, $link);
    }

    /**
     * Resolve the symlink at $path.
     *
     * @param string $path
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<string>
     */
    public function resolveSymlink(string $path): Promise
    {
        return $this->driver->resolveSymlink($path);
    }

    /**
     * Move / rename a file or directory.
     *
     * @param string $from
     * @param string $to
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function move(string $from, string $to): Promise
    {
        return $this->driver->move($from, $to);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function deleteFile(string $path): Promise
    {
        return $this->driver->deleteFile($path);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int    $mode
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function createDirectory(string $path, int $mode = 0777): Promise
    {
        return $this->driver->createDirectory($path, $mode);
    }

    /**
     * Create a directory recursively.
     *
     * @param string $path
     * @param int    $mode
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function createDirectoryRecursively(string $path, int $mode = 0777): Promise
    {
        return $this->driver->createDirectoryRecursively($path, $mode);
    }

    /**
     * Delete a directory.
     *
     * @param string $path
     * @fails \Amp\Files\FilesystemException If the operation fails.
     *
     * @return Promise<void>
     */
    public function deleteDirectory(string $path): Promise
    {
        return $this->driver->deleteDirectory($path);
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
    public function listFiles(string $path): Promise
    {
        return $this->driver->listFiles($path);
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
    public function changePermissions(string $path, int $mode): Promise
    {
        return $this->driver->changePermissions($path, $mode);
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
    public function changeOwner(string $path, ?int $uid, ?int $gid = null): Promise
    {
        return $this->driver->changeOwner($path, $uid, $gid);
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
     *
     * @return Promise<void>
     */
    public function touch(string $path, ?int $modificationTime = null, ?int $accessTime = null): Promise
    {
        return $this->driver->touch($path, $modificationTime, $accessTime);
    }

    /**
     * Buffer the specified file's contents.
     *
     * @param string $path The file path from which to buffer contents.
     *
     * @return Promise<string>
     */
    public function read(string $path): Promise
    {
        return $this->driver->read($path);
    }

    /**
     * Write the contents string to the specified path.
     *
     * @param string $path The file path to which to $contents should be written.
     * @param string $contents The data to write to the specified $path.
     *
     * @return Promise<void>
     */
    public function write(string $path, string $contents): Promise
    {
        return $this->driver->write($path, $contents);
    }
}
