<?php

namespace Amp\File\Sync;

use Amp\File\FilesystemException;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;
use Amp\Sync\SyncException;
use function Amp\delay;
use function Amp\File\openFile;
use function Amp\File\deleteFile;

final class KeyedFileMutex implements KeyedMutex
{
    private const LATENCY_TIMEOUT = 10;

    private string $pattern;

    /**
     * @param string $pattern Name of temporary file to use as a mutex, including a %s to splice the key in
     */
    public function __construct(string $pattern)
    {
        if (!preg_match("((%%(*SKIP))*%s)", $pattern)) {
            throw new \Error("Invalid pattern for a mutex, needs to contain an unescaped %s");
        }

        $this->pattern = $pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(string $key): Lock
    {
        $filename = sprintf($this->pattern, hash('sha256', $key));

        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        while (true) {
            try {
                $file = openFile($filename, 'x');
                break;
            } catch (FilesystemException $exception) {
                delay(self::LATENCY_TIMEOUT);
            }
        }

        // Return a lock object that can be used to release the lock on the mutex.
        $lock = new Lock(0, fn() => $this->release($filename));

        $file->close();

        return $lock;
    }

    /**
     * Releases the lock on the mutex.
     */
    private function release($filename): void
    {
        try {
            deleteFile($filename);
        } catch (\Throwable $exception) {
            throw new SyncException(
                'Failed to unlock the mutex file: ' . $filename,
                0,
                $exception
            );
        }
    }
}
