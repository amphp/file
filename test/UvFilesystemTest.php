<?php

namespace Amp\Fs\Test;

use Amp\{ Reactor, UvReactor };
use Amp\Fs\{ Filesystem, UvFilesystem };

class UvFilesystemTest extends FilesystemTest {
    protected function getReactor(): Reactor {
        return new UvReactor;
    }
    protected function getFilesystem(Reactor $reactor): Filesystem {
        return new UvFilesystem($reactor);
    }
    
    public function testScandir() {
        $this->markTestSkipped("currently crashes php");
    }
}
