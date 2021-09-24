<?php declare(strict_types = 1);

namespace Amp\File\Test\Sync;

use Amp\File\Sync\FileMutex;
use Amp\Sync\Mutex;
use Amp\Sync\Test\AbstractMutexTest;

final class FileMutexTest extends AbstractMutexTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->ignoreLoopWatchers();
    }

    public function createMutex(): Mutex
    {
        return new FileMutex(\tempnam(\sys_get_temp_dir(), 'mutex-') . '.lock');
    }
}
