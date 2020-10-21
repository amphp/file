<?php

namespace Amp\File\Driver;

use Amp\File\Driver;
use Amp\File\Internal\Cache;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class StatusCachingDriver implements Driver
{
    /** @var Driver */
    private $driver;

    /** @var Cache */
    private $statusCache;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
        $this->statusCache = new Cache(1000, 1024);
    }

    public function openFile(string $path, string $mode): Promise
    {
        return call(function () use ($path, $mode) {
            $file = yield $this->driver->openFile($path, $mode);

            return new StatusCachingFile($file, function () use ($path) {
                $this->invalidate([$path], new Success);
            });
        });
    }

    public function getStatus(string $path): Promise
    {
        if ($cachedStat = $this->statusCache->get($path)) {
            return new Success($cachedStat);
        }

        return call(function () use ($path) {
            $stat = yield $this->driver->getStatus($path);
            if ($stat) {
                $this->statusCache->set($path, $stat, 1000);
            }

            return $stat;
        });
    }

    public function getLinkStatus(string $path): Promise
    {
        return $this->driver->getLinkStatus($path);
    }

    public function createSymlink(string $target, string $link): Promise
    {
        return $this->invalidate([$target, $link], $this->driver->createSymlink($target, $link));
    }

    public function createHardlink(string $target, string $link): Promise
    {
        return $this->invalidate([$target, $link], $this->driver->createHardlink($target, $link));
    }

    public function resolveSymlink(string $target): Promise
    {
        return $this->driver->resolveSymlink($target);
    }

    public function move(string $from, string $to): Promise
    {
        return $this->invalidate([$from, $to], $this->driver->move($from, $to));
    }

    public function deleteFile(string $path): Promise
    {
        return $this->invalidate([$path], $this->driver->deleteFile($path));
    }

    public function createDirectory(string $path, int $mode = 0777): Promise
    {
        return $this->invalidate([$path], $this->driver->createDirectory($path, $mode));
    }

    public function createDirectoryRecursively(string $path, int $mode = 0777): Promise
    {
        return $this->invalidate([$path], $this->driver->createDirectoryRecursively($path, $mode));
    }

    public function deleteDirectory(string $path): Promise
    {
        return $this->invalidate([$path], $this->driver->deleteDirectory($path));
    }

    public function listFiles(string $path): Promise
    {
        return $this->driver->listFiles($path);
    }

    public function changePermissions(string $path, int $mode): Promise
    {
        return $this->invalidate([$path], $this->driver->changePermissions($path, $mode));
    }

    public function changeOwner(string $path, ?int $uid, ?int $gid): Promise
    {
        return $this->invalidate([$path], $this->driver->changeOwner($path, $uid, $gid));
    }

    public function touch(string $path, ?int $modificationTime, ?int $accessTime): Promise
    {
        return $this->invalidate([$path], $this->driver->touch($path, $modificationTime, $accessTime));
    }

    public function read(string $path): Promise
    {
        return $this->driver->read($path);
    }

    public function write(string $path, string $contents): Promise
    {
        return $this->invalidate([$path], $this->driver->write($path, $contents));
    }

    private function invalidate(array $paths, Promise $promise): Promise
    {
        foreach ($paths as $path) {
            $this->statusCache->delete($path);
        }

        if (!$promise instanceof Success) {
            $promise->onResolve(function () use ($paths) {
                foreach ($paths as $path) {
                    $this->statusCache->delete($path);
                }
            });
        }

        return $promise;
    }
}
