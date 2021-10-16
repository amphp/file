<?php

namespace Amp\File\Internal;

use Revolt\EventLoop\Driver\UvDriver as UvLoopDriver;

/** @internal */
final class UvPoll
{
    private string $watcher;

    private int $requests = 0;

    public function __construct(private UvLoopDriver $driver)
    {
        // Create dummy watcher to keep loop running while polling.
        $this->watcher = $this->driver->repeat(\PHP_INT_MAX / 2, static fn () => null);

        $this->driver->disable($this->watcher);
    }

    public function __destruct()
    {
        $this->driver->cancel($this->watcher);
    }

    public function listen(): void
    {
        if ($this->requests++ === 0) {
            $this->driver->enable($this->watcher);
        }
    }

    public function done(): void
    {
        if (--$this->requests === 0) {
            $this->driver->disable($this->watcher);
        }

        \assert($this->requests >= 0);
    }
}
