<?php declare(strict_types = 1);

namespace Amp\File\Test\Sync;

use Amp\Sync\FileMutex;
use Amp\Sync\Mutex;
use Amp\Sync\Test\AbstractMutexTest;

final class AsyncFileMutexTest extends AbstractMutexTest
{
    public function createMutex(): Mutex
    {
        return new FileMutex(\tempnam(\sys_get_temp_dir(), 'mutex-') . '.lock');
    }
}
