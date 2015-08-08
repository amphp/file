<?php

namespace Amp\File\Test;

class EioDriverTest extends DriverTest {
    protected function setUp() {
        if (extension_loaded("eio")) {
            \Amp\reactor(\Amp\driver());
            \Amp\File\filesystem(new \Amp\File\EioDriver);
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
    
    public function testLstat() {
        $this->markTestSkipped();
    }
    
    public function testDirStat() {
        $this->markTestSkipped();
    }
    
    public function testNonexistentPathStatResolvesToNull() {
        $this->markTestSkipped();
    }

    public function testExists() {
        $this->markTestSkipped();
    }

    public function testSize() {
        $this->markTestSkipped();
    }
}
