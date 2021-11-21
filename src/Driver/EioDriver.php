<?php

namespace Amp\File\Driver;

use Amp\Deferred;
use Amp\File\Driver;
use Amp\File\File;
use Amp\File\FilesystemException;
use Amp\File\Internal;
use Revolt\EventLoop\Driver as EventLoopDriver;

final class EioDriver implements Driver
{
    /**
     * @return bool Determines if this driver can be used based on the environment.
     */
    public static function isSupported(): bool
    {
        return \extension_loaded('eio');
    }

    private Internal\EioPoll $poll;

    public function __construct(EventLoopDriver $driver)
    {
        $this->poll = new Internal\EioPoll($driver);
    }

    public function openFile(string $path, string $mode): File
    {
        $flags = \EIO_O_NONBLOCK | $this->parseMode($mode);
        if (\defined('\EIO_O_FSYNC')) {
            $flags |= \EIO_O_FSYNC;
        }

        $chmod = ($flags & \EIO_O_CREAT) ? 0644 : 0;

        $deferred = new Deferred;
        $this->poll->listen();

        $openArr = [$mode, $path, $deferred];
        \eio_open($path, $flags, $chmod, \EIO_PRI_DEFAULT, [$this, "onOpenHandle"], $openArr);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function getStatus(string $path): ?array
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        \eio_stat($path, $priority, [$this, "onStat"], $data);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function getLinkStatus(string $path): ?array
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_lstat($path, $priority, [$this, "onLstat"], $deferred);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function createSymlink(string $target, string $link): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_symlink($target, $link, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function createHardlink(string $target, string $link): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_link($target, $link, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function resolveSymlink(string $path): string
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_readlink($path, $priority, [$this, "onReadlink"], $deferred);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function move(string $from, string $to): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_rename($from, $to, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function deleteFile(string $path): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        $result = \eio_unlink($path, $priority, [$this, "onUnlink"], $data);
        // For a non-existent file eio_unlink immediately returns true and the callback is never called.
        if ($result === true) {
            $deferred->error(new FilesystemException('File does not exist.'));
        }

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function createDirectory(string $path, int $mode = 0777): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        \eio_mkdir($path, $mode, \EIO_PRI_DEFAULT, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function createDirectoryRecursively(string $path, int $mode = 0777): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;

        $path = \str_replace("/", DIRECTORY_SEPARATOR, $path);
        $arrayPath = \explode(DIRECTORY_SEPARATOR, $path);
        $tmpPath = "";

        $callback = function () use (
            &$callback,
            &$arrayPath,
            &$tmpPath,
            $mode,
            $priority,
            $deferred
        ): void {
            $tmpPath .= DIRECTORY_SEPARATOR . \array_shift($arrayPath);

            if (empty($arrayPath)) {
                \eio_mkdir($tmpPath, $mode, $priority, [$this, "onGenericResult"], $deferred);
            } else {
                \eio_mkdir($tmpPath, $mode, $priority, $callback);
            }
        };

        $callback();

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function deleteDirectory(string $path): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        $data = [$deferred, $path];
        \eio_rmdir($path, $priority, [$this, "onRmdir"], $data);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function listFiles(string $path): array
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $flags = \EIO_READDIR_STAT_ORDER | \EIO_READDIR_DIRS_FIRST;
        $priority = \EIO_PRI_DEFAULT;
        \eio_readdir($path, $flags, $priority, [$this, "onScandir"], $deferred);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function changePermissions(string $path, int $mode): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_chmod($path, $mode, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): void
    {
        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_chown($path, $uid ?? -1, $gid ?? -1, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): void
    {
        $modificationTime = $modificationTime ?? \time();
        $accessTime = $accessTime ?? $modificationTime;

        $deferred = new Deferred;
        $this->poll->listen();

        $priority = \EIO_PRI_DEFAULT;
        \eio_utime($path, $accessTime, $modificationTime, $priority, [$this, "onGenericResult"], $deferred);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function read(string $path): string
    {
        $flags = $flags = \EIO_O_RDONLY;
        $mode = 0;
        $priority = \EIO_PRI_DEFAULT;

        $deferred = new Deferred;
        $this->poll->listen();

        \eio_open($path, $flags, $mode, $priority, [$this, "onGetOpen"], $deferred);

        try {
            return $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    public function write(string $path, string $contents): void
    {
        $flags = \EIO_O_RDWR | \EIO_O_CREAT;
        $mode = \EIO_S_IRUSR | \EIO_S_IWUSR | \EIO_S_IXUSR;
        $priority = \EIO_PRI_DEFAULT;

        $deferred = new Deferred;
        $this->poll->listen();

        $data = [$contents, $deferred];
        \eio_open($path, $flags, $mode, $priority, [$this, "onPutOpen"], $data);

        try {
            $deferred->getFuture()->await();
        } finally {
            $this->poll->done();
        }
    }

    private function parseMode(string $mode): int
    {
        $mode = \str_replace(['b', 't', 'e'], '', $mode);

        switch ($mode) {
            case 'r':
                return \EIO_O_RDONLY;
            case 'r+':
                return \EIO_O_RDWR;
            case 'w':
                return \EIO_O_WRONLY | \EIO_O_TRUNC | \EIO_O_CREAT;
            case 'w+':
                return \EIO_O_RDWR | \EIO_O_TRUNC | \EIO_O_CREAT;
            case 'a':
                return \EIO_O_WRONLY | \EIO_O_APPEND | \EIO_O_CREAT;
            case 'a+':
                return \EIO_O_RDWR | \EIO_O_APPEND | \EIO_O_CREAT;
            case 'x':
                return \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_EXCL;
            case 'x+':
                return \EIO_O_RDWR | \EIO_O_CREAT | \EIO_O_EXCL;
            case 'c':
                return \EIO_O_WRONLY | \EIO_O_CREAT;
            case 'c+':
                return \EIO_O_RDWR | \EIO_O_CREAT;
            default:
                throw new \Error('Invalid file mode: ' . $mode);
        }
    }

    private function onOpenHandle(array $openArr, $result, $req): void
    {
        [$mode, $path, $deferred] = $openArr;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } elseif ($mode[0] === "a") {
            \array_unshift($openArr, $result);
            \eio_ftruncate($result, $offset = 0, \EIO_PRI_DEFAULT, [$this, "onOpenFtruncate"], $openArr);
        } else {
            \array_unshift($openArr, $result);
            \eio_fstat($result, \EIO_PRI_DEFAULT, [$this, "onOpenFstat"], $openArr);
        }
    }

    private function onOpenFtruncate(array $openArr, $result, $req): void
    {
        [$fh, $mode, $path, $deferred] = $openArr;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $handle = new EioFile($this->poll, $fh, $path, $mode, $size = 0);
            $deferred->complete($handle);
        }
    }

    private function onOpenFstat(array $openArr, $result, $req): void
    {
        [$fh, $mode, $path, $deferred] = $openArr;
        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $handle = new EioFile($this->poll, $fh, $path, $mode, $result["size"]);
            $deferred->complete($handle);
        }
    }

    private function onStat(array $data, $result, $req): void
    {
        [$deferred, $path] = $data;
        if ($result === -1) {
            $deferred->complete(null);
        } else {
            $deferred->complete($result);
        }
    }

    private function onLstat(Deferred $deferred, $result, $req): void
    {
        if ($result === -1) {
            $deferred->complete(null);
        } else {
            $deferred->complete($result);
        }
    }

    private function onReadlink(Deferred $deferred, $result, $req): void
    {
        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete($result);
        }
    }

    private function onGenericResult(Deferred $deferred, $result, $req): void
    {
        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete(null);
        }
    }

    private function onUnlink(array $data, $result, $req): void
    {
        [$deferred, $path] = $data;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete(null);
        }
    }

    private function onRmdir(array $data, $result, $req): void
    {
        [$deferred, $path] = $data;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete(null);
        }
    }

    private function onScandir(Deferred $deferred, $result, $req): void
    {
        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $result = $result["names"];
            \sort($result);
            $deferred->complete($result);
        }
    }

    private function onGetOpen(Deferred $deferred, $result, $req): void
    {
        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $priority = \EIO_PRI_DEFAULT;
            \eio_fstat($result, $priority, [$this, "onGetFstat"], [$result, $deferred]);
        }
    }

    private function onGetFstat(array $fhAndPromisor, $result, $req): void
    {
        [$fh, $deferred] = $fhAndPromisor;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
            return;
        }

        $offset = 0;
        $length = $result["size"];
        $priority = \EIO_PRI_DEFAULT;
        \eio_read($fh, $length, $offset, $priority, [$this, "onGetRead"], $fhAndPromisor);
    }

    private function onGetRead(array $fhAndPromisor, $result, $req): void
    {
        [$fh, $deferred] = $fhAndPromisor;

        \eio_close($fh);

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete($result);
        }
    }

    private function onPutOpen(array $data, $result, $req): void
    {
        [$contents, $deferred] = $data;

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $length = \strlen($contents);
            $offset = 0;
            $priority = \EIO_PRI_DEFAULT;
            $callback = [$this, "onPutWrite"];
            $fhAndPromisor = [$result, $deferred];
            \eio_write($result, $contents, $length, $offset, $priority, $callback, $fhAndPromisor);
        }
    }

    private function onPutWrite(array $fhAndPromisor, $result, $req): void
    {
        [$fh, $deferred] = $fhAndPromisor;

        \eio_close($fh);

        if ($result === -1) {
            $deferred->error(new FilesystemException(\eio_get_last_error($req)));
        } else {
            $deferred->complete(null);
        }
    }
}
