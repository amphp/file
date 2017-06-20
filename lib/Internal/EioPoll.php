<?php

namespace Amp\File\Internal;

use Amp\Loop;

class EioPoll {
    /** @var resource */
    private static $stream;
    
    /** @var string */
    private $watcher;
    
    /** @var int */
    private $requests = 0;
    
    public function __construct() {
        if (!self::$stream) {
            \eio_init();
            self::$stream = \eio_get_event_stream();
        }

        $this->watcher = Loop::onReadable(self::$stream, function () {
            while (\eio_npending()) {
                \eio_poll();
            }
        });
        
        Loop::disable($this->watcher);
    }
    
    public function listen() {
        if ($this->requests++ === 0) {
            Loop::enable($this->watcher);
        }
    }
    
    public function done() {
        if (--$this->requests === 0) {
            Loop::disable($this->watcher);
        }
    }
    
    public function __destruct() {
        Loop::cancel($this->watcher);
    }
}