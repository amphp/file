<?php

namespace Amp\File\Sync;

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Promise;
use Amp\Sync\Mutex;
use Amp\Sync\SyncException;
use function Amp\File\open;
use function Amp\File\unlink;

final class AsyncFileMutex implements Mutex
{
    public const LATENCY_TIMEOUT = 10;

    /** @var string The full path to the lock file. */
    private $fileName;

    /**
     * @param string|null $fileName Name of temporary file to use as a mutex.
     */
    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Promise
    {
        return new Coroutine($this->doAcquire());
    }

    /**
     * @coroutine
     *
     * @return \Generator
     */
    private function doAcquire(): \Generator
    {
        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        while (($file = yield open($this->fileName, 'x')) === false) {
            yield new Delayed(self::LATENCY_TIMEOUT);
        }

        // Return a lock object that can be used to release the lock on the mutex.
        $lock = new Lock(0, function (): void {
            $this->release();
        });

        yield $file->close();

        return $lock;
    }

    /**
     * Releases the lock on the mutex.
     *
     * @throws SyncException If the unlock operation failed.
     */
    private function release(): void
    {
        unlink($this->fileName)->onResolve(
            function (?\Throwable $exception) {
                if ($exception !== null) {
                    throw new SyncException(
                        'Failed to unlock the mutex file: ' . $this->file,
                        0,
                        $exception
                    );
                }
            }
        );
    }
}
