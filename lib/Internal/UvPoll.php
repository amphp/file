<?php

namespace Amp\File\Internal;

use Amp\Loop;
use Amp\Promise;

final class UvPoll
{
    /** @var string */
    private $watcher;

    /** @var int */
    private $requests = 0;

    public function __construct()
    {
        $this->watcher = Loop::repeat(\PHP_INT_MAX / 2, static function (): void {
            // do nothing, it's a dummy watcher
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
