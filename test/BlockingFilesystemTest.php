<?php

namespace Amp\Fs\Test;

class BlockingFilesystemTest extends FilesystemTest {
    protected function setUp() {
        \Amp\reactor(\Amp\init());
        \Amp\Fs\filesystem(new \Amp\Fs\BlockingFilesystem);
    }
}
