<?php declare(strict_types=1);

namespace Amp\File;

use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;
use Amp\Sync\SyncException;
use function Amp\delay;

final class KeyedFileMutex implements KeyedMutex
{
    private const LATENCY_TIMEOUT = 0.01;

    private readonly Filesystem $filesystem;

    /**
     * @param string $pattern Name of temporary file to use as a mutex, including an %s to splice the key in
     */
    public function __construct(private readonly string $pattern, ?Filesystem $filesystem = null)
    {
        if (!\preg_match("((%%(*SKIP))*%s)", $this->pattern)) {
            throw new \Error("Invalid pattern for a mutex, needs to contain an unescaped %s");
        }

        $this->filesystem = $filesystem ?? filesystem();
    }

    public function acquire(string $key): Lock
    {
        $filename = \sprintf($this->pattern, \hash('sha256', $key));

        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        while (true) {
            try {
                $file = $this->filesystem->openFile($filename, 'x');

                // Return a lock object that can be used to release the lock on the mutex.
                $lock = new Lock(fn() => $this->release($filename));

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
    private function release(string $filename): void
    {
        try {
            $this->filesystem->deleteFile($filename);
        } catch (\Throwable $exception) {
            throw new SyncException(
                'Failed to unlock the mutex file: ' . $filename,
                previous: $exception,
            );
        }
    }
}
