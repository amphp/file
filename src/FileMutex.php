<?php declare(strict_types=1);

namespace Amp\File;

use Amp\Sync\Lock;
use Amp\Sync\Mutex;
use Amp\Sync\SyncException;
use function Amp\delay;

final class FileMutex implements Mutex
{
    private const LATENCY_TIMEOUT = 0.01;
    private const DELAY_LIMIT = 1;

    private readonly Filesystem $filesystem;

    /**
     * @param string $fileName Name of temporary file to use as a mutex.
     */
    public function __construct(private readonly string $fileName, ?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? filesystem();
    }

    public function acquire(): Lock
    {
        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        for ($attempt = 0; true; ++$attempt) {
            try {
                $file = $this->filesystem->openFile($this->fileName, 'x');

                // Return a lock object that can be used to release the lock on the mutex.
                $lock = new Lock($this->release(...));

                $file->close();

                return $lock;
            } catch (FilesystemException) {
                delay(min(self::DELAY_LIMIT, self::LATENCY_TIMEOUT * (2 ** $attempt)));
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
            $this->filesystem->deleteFile($this->fileName);
        } catch (\Throwable $exception) {
            throw new SyncException(
                'Failed to unlock the mutex file: ' . $this->fileName,
                previous: $exception,
            );
        }
    }
}
