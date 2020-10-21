<?php

namespace Amp\File\Test\Driver;

use Amp\File;
use Amp\File\Driver\UvDriver;
use Amp\File\Test\DriverTest;
use Amp\Loop;

class UvDriverTest extends DriverTest
{
    /**
     * @dataProvider symlinkPathProvider
     *
     * @param \Closure $linkResolver
     */
    public function testResolveSymlinkError(\Closure $linkResolver): \Generator
    {
        if (\version_compare(\phpversion('uv'), '0.3.0', '<')) {
            $this->markTestSkipped('UvDriver Test Skipped: Causes Crash');
        }

        yield from parent::testResolveSymlinkError($linkResolver);
    }

    protected function createDriver(): File\Driver
    {
        if (!\extension_loaded("uv")) {
            $this->markTestSkipped("php-uv extension not loaded");
        }

        $loop = new Loop\UvDriver;

        Loop::set($loop);

        return new UvDriver($loop);
    }
}
