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

    /** @var Loop\Driver */
    private $driver;

    public function __construct(Loop\Driver $driver)
    {
        $this->driver = $driver;

        if (!self::$stream) {
            if (\function_exists('eio_init')) {
                \eio_init();
            }
            self::$stream = \eio_get_event_stream();
        }

        $this->watcher = $this->driver->onReadable(self::$stream, static function (): void {
            while (\eio_npending()) {
                \eio_poll();
            }
        });

        $this->driver->disable($this->watcher);

        $this->driver->setState(self::class, new class($this->watcher, $driver) {
            private $watcher;
            private $driver;

            public function __construct(string $watcher, Loop\Driver $driver)
            {
                $this->watcher = $watcher;
                $this->driver = $driver;
            }

            public function __destruct()
            {
                $this->driver->cancel($this->watcher);

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
            $this->driver->enable($this->watcher);
        }

        $promise->onResolve(\Closure::fromCallable([$this, 'done']));
    }

    private function done(): void
    {
        if (--$this->requests === 0) {
            $this->driver->disable($this->watcher);
        }

        \assert($this->requests >= 0);
    }
}
