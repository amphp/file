<?php

namespace Amp\File\Internal;

use Amp\Loop\UvDriver;
use Amp\Promise;

/** @internal */
final class UvPoll
{
    private string $watcher;

    private int $requests = 0;

    private UvDriver $driver;

    public function __construct(UvDriver $driver)
    {
        $this->driver = $driver;

        $this->watcher = $this->driver->repeat(\PHP_INT_MAX / 2, static function (): void {
            // do nothing, it's a dummy watcher
        });

        $this->driver->disable($this->watcher);

        $this->driver->setState(self::class, new class($this->watcher, $driver) {
            private string $watcher;
            private UvDriver $driver;

            public function __construct(string $watcher, UvDriver $driver)
            {
                $this->watcher = $watcher;
                $this->driver = $driver;
            }

            public function __destruct()
            {
                $this->driver->cancel($this->watcher);
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
