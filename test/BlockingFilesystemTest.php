<?php

namespace Amp\Fs\Test;

use Amp\{ Reactor, NativeReactor };
use Amp\Fs\{ Filesystem, BlockingFilesystem };

class BlockingFilesystemTest extends FilesystemTest {
    protected function getReactor(): Reactor {
        return new NativeReactor;
    }
    protected function getFilesystem(Reactor $reactor): Filesystem {
        return new BlockingFilesystem($reactor);
    }
}
