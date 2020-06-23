<?php

namespace Amp\File\Internal;

use Amp\Loop;
use Amp\Promise;

/** @internal */
final class EioPoll
{
    /** @var resource */
    private static $stream;

    /** @var string */
    private $watcher;

    /** @var int */
    private $requests = 0;

    public function __construct()
    {
        if (!self::$stream) {
            \eio_init();
            self::$stream = \eio_get_event_stream();
        }

        $this->watcher = Loop::onReadable(self::$stream, static function (): void {
            while (\eio_npending()) {
                \eio_poll();
            }
        });

        Loop::disable($this->watcher);

        Loop::setState(self::class, new class($this->watcher) {
            private $watcher;

            public function __construct(string $watcher)
            {
                $this->watcher = $watcher;
            }

            public function __destruct()
            {
                Loop::cancel($this->watcher);

                // Ensure there are no active operations anymore. This is a safe-guard as some operations might not be
                // finished on loop exit due to not being yielded. This also ensures a clean shutdown for these if PHP
                // exists.
                \eio_event_loop();
            }
        });
    }

    public function listen(Promise $promise): void
    {
        if ($this->requests++ === 0) {
            Loop::enable($this->watcher);
        }

        $promise->onResolve(\Closure::fromCallable([$this, 'done']));
    }

    private function done(): void
    {
        if (--$this->requests === 0) {
            Loop::disable($this->watcher);
        }

        \assert($this->requests >= 0);
    }
}
