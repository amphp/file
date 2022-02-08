<?php

namespace Amp\File;

use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use Amp\Sync\SyncException;
use function Amp\delay;

final class FileMutex implements Mutex
{
    private const LATENCY_TIMEOUT = 0.01;

    /** @var string The full path to the lock file. */
    private string $fileName;

    /**
     * @param string $fileName Name of temporary file to use as a mutex.
     */
    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    public function acquire(): Lock
    {
        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        while (true) {
            try {
                $file = openFile($this->fileName, 'x');

                // Return a lock object that can be used to release the lock on the mutex.
                $lock = new Lock(fn () => $this->release());

                $file->close();

                return $lock;
            } catch (FilesystemException) {
                delay(self::LATENCY_TIMEOUT);
            }
        }
    }

    /**
     * Releases the lock on the mutex.
     *
     * @throws SyncException
     */
    private function release(): void
    {
        try {
            deleteFile($this->fileName);
        } catch (\Throwable $exception) {
            throw new SyncException(
                'Failed to unlock the mutex file: ' . $this->fileName,
                0,
                $exception
            );
        }
    }
}
