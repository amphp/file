<?php declare(strict_types=1);

namespace Amp\File;

use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use function Amp\delay;

final class FileMutex implements Mutex
{
    private const LATENCY_TIMEOUT = 0.01;

    /**
     * @param string $fileName Name of temporary file to use as a mutex.
     */
    public function __construct(private readonly string $fileName)
    {
    }

    public function acquire(): Lock
    {
        $f = \fopen($this->fileName, 'c');
        while (true) {
            if (\flock($f, LOCK_EX|LOCK_NB)) {
                // Return a lock object that can be used to release the lock on the mutex.
                $lock = new Lock(fn () => \flock($f, LOCK_UN));

                return $lock;
            }

            delay(self::LATENCY_TIMEOUT);
        }
    }
}
