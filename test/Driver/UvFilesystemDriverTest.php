<?php declare(strict_types=1);

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\UvFilesystemDriver;
use Amp\File\Test\FilesystemDriverTest;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\UvDriver as UvLoopDriver;

class UvFilesystemDriverTest extends FilesystemDriverTest
{
    /**
     * @dataProvider symlinkPathProvider
     *
     */
    public function testResolveSymlinkError(\Closure $linkResolver): void
    {
        if (\version_compare(\phpversion('uv'), '0.3.0', '<')) {
            $this->markTestSkipped('UvDriver Test Skipped: Causes Crash');
        }

        parent::testResolveSymlinkError($linkResolver);
    }

    protected function createDriver(): File\FilesystemDriver
    {
        if (!\extension_loaded("uv")) {
            $this->markTestSkipped("ext-uv not loaded");
        }

        $loop = EventLoop::getDriver();

        if (!$loop instanceof UvLoopDriver) {
            $this->markTestSkipped("Loop driver must be using ext-uv");
        }

        return new UvFilesystemDriver($loop);
    }

    public function testCreateDirectoryRecursivelyWithAbsolutePath(): void
    {
        $driver = $this->createDriver();

        $baseDir = \sys_get_temp_dir();

        $dir = $baseDir . DIRECTORY_SEPARATOR . 'amp-test-' . \uniqid('', true) . DIRECTORY_SEPARATOR . 'nested';

        try {
            $driver->createDirectoryRecursively($dir);
            $status = $driver->getStatus($dir);
            $this->assertNotNull($status, 'Directory should exist');

            $isDir = ($status['mode'] & \UV::S_IFDIR) !== 0;
            $this->assertTrue($isDir, 'Path must be a directory');

        } finally {
            if ($driver->getStatus($dir)) {
                $driver->deleteDirectory($dir);
            }

            $parent = \dirname($dir);
            if ($parent !== $baseDir && \str_starts_with($parent, $baseDir) && $driver->getStatus($parent)) {
                $driver->deleteDirectory($parent);
            }
        }
    }
}
