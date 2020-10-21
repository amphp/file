<?php

namespace Amp\File\Sync;

use Amp\Coroutine;
use Amp\Delayed;
use Amp\File\FilesystemException;
use Amp\Promise;
use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use Amp\Sync\SyncException;
use function Amp\File\deleteFile;
use function Amp\File\openFile;

final class AsyncFileMutex implements Mutex
{
    private const LATENCY_TIMEOUT = 10;

    /** @var string The full path to the lock file. */
    private $fileName;

    /**
     * @param string $fileName Name of temporary file to use as a mutex.
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
        while (true) {
            try {
                $file = yield openFile($this->fileName, 'x');

                break;
            } catch (FilesystemException $exception) {
                yield new Delayed(self::LATENCY_TIMEOUT);
            }
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
     */
    private function release(): void
    {
        deleteFile($this->fileName)->onResolve(
            function (?\Throwable $exception): void {
                if ($exception !== null) {
                    throw new SyncException(
                        'Failed to unlock the mutex file: ' . $this->fileName,
                        0,
                        $exception
                    );
                }
            }
        );
    }
}
