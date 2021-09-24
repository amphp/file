<?php declare(strict_types = 1);

namespace Amp\File\Test\Sync;

use Amp\File\Sync\KeyedFileMutex;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Test\AbstractKeyedMutexTest;

final class KeyedFileMutexTest extends AbstractKeyedMutexTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->ignoreLoopWatchers();
    }

    public function createMutex(): KeyedMutex
    {
        return new KeyedFileMutex(\sys_get_temp_dir() . '/testmutex-' . bin2hex(random_bytes(5)) . '-%s.lock');
    }
}
