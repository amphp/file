<?php

namespace Amp\Fs\Test;

class EioFilesystemTest extends FilesystemTest {
    protected function setUp() {
        if (extension_loaded("eio")) {
            \Amp\reactor(\Amp\init());
            \Amp\Fs\filesystem(new \Amp\Fs\EioFilesystem);
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
}
