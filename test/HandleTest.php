<?php

namespace Amp\File\Test;

use Amp\ByteStream\ClosedException;
use Amp\Delayed;
use Amp\File;
use Amp\PHPUnit\TestCase;
use Amp\TimeoutCancellationToken;

use function Amp\Promise\timeout;

abstract class HandleTest extends TestCase
{
    protected function setUp()
    {
        Fixture::init();
        File\StatCache::clear();
    }

    protected function tearDown()
    {
        Fixture::clear();
    }

    abstract protected function execute(callable $cb);

    public function testWrite()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");
            $this->assertSame(0, $handle->tell());

            $handle->write("foo");
            yield $handle->write("bar");
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertSame(6, $handle->tell());
            $this->assertTrue($handle->eof());
            $this->assertSame("foobar", $contents);

            yield $handle->close();
        });
    }

    public function testEmptyWrite()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";

            $handle = yield File\open($path, "c+");
            $this->assertSame(0, $handle->tell());

            yield $handle->write("");
            $this->assertSame(0, $handle->tell());

            yield $handle->close();
        });
    }

    public function testWriteAfterClose()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");
            yield $handle->close();

            $this->expectException(ClosedException::class);
            yield $handle->write("bar");
        });
    }

    public function testDoubleClose()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");
            yield $handle->close();
            $this->assertNull(yield $handle->close());
        });
    }

    public function testWriteAfterEnd()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");
            $this->assertSame(0, $handle->tell());
            yield $handle->end("foo");

            $this->expectException(ClosedException::class);
            yield $handle->write("bar");
        });
    }

    public function testWriteInAppendMode()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "a+");
            $this->assertSame(0, $handle->tell());
            yield $handle->write("bar");
            yield $handle->write("foo");
            yield $handle->write("baz");
            $this->assertSame(9, $handle->tell());
            yield $handle->seek(0);
            $this->assertSame(0, $handle->tell());
            $this->assertSame("barfoobaz", yield $handle->read());
        });
    }

    public function testReadingToEof()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $contents = "";
            $position = 0;

            $stat = yield File\stat(__FILE__);
            $chunkSize = (int) \floor(($stat["size"] / 5));

            while (!$handle->eof()) {
                $chunk = yield $handle->read($chunkSize);
                $contents .= $chunk;
                $position += \strlen($chunk);
                $this->assertSame($position, $handle->tell());
            }

            $this->assertNull(yield $handle->read());
            $this->assertSame(yield File\get(__FILE__), $contents);

            yield $handle->close();
        });
    }

    public function testSequentialReads()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $contents = "";
            $contents .= yield $handle->read(10);
            $contents .= yield $handle->read(10);

            $expected = \substr(yield File\get(__FILE__), 0, 20);
            $this->assertSame($expected, $contents);

            yield $handle->close();
        });
    }

    public function testReadingFromOffset()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            $chunk = yield $handle->read(90);
            $this->assertSame(100, $handle->tell());
            $expected = \substr(yield File\get(__FILE__), 10, 90);
            $this->assertSame($expected, $chunk);

            yield $handle->close();
        });
    }

    /**
     * @expectedException \Error
     */
    public function testSeekThrowsOnInvalidWhence()
    {
        $this->execute(function () {
            try {
                /** @var \Amp\File\Handle $handle */
                $handle = yield File\open(__FILE__, "r");
                yield $handle->seek(0, 99999);
            } finally {
                yield $handle->close();
            }
        });
    }

    public function testSeekSetCur()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            yield $handle->seek(-10, \SEEK_CUR);
            $this->assertSame(0, $handle->tell());
            yield $handle->close();
        });
    }

    public function testSeekSetEnd()
    {
        $this->execute(function () {
            $size = yield File\size(__FILE__);
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(-10, \SEEK_END);
            $this->assertSame($size - 10, $handle->tell());
            yield $handle->close();
        });
    }

    public function testPath()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(__FILE__, $handle->path());
            yield $handle->close();
        });
    }

    public function testMode()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame("r", $handle->mode());
            yield $handle->close();
        });
    }
    /**
     * Try locking file exclusively.
     *
     * @param string $file File
     * @param int $polling Polling interval
     * @param int $timeout Lock timeout
     * @return void
     */
    private function tryLockExclusive(string $file, int $polling, int $timeout)
    {
        return File\lockExclusive($file, $polling, new TimeoutCancellationToken($timeout));
    }
    /**
     * Try locking file in shared mode.
     *
     * @param string $file File
     * @param int $polling Polling interval
     * @param int $timeout Lock timeout
     * @return void
     */
    private function tryLockShared(string $file, int $polling, int $timeout)
    {
        return File\lockShared($file, $polling, new TimeoutCancellationToken($timeout));
    }
    public function testExclusiveLock()
    {
        $this->execute(function () {
            $primary = null;
            $secondary = null;
            try {
                try {
                    $primary = yield $this->tryLockExclusive(__FILE__, 100, 100);
                    $this->assertInstanceOf(\Closure::class, $primary);

                    $unlocked = false;
                    $try = $this->tryLockShared(__FILE__, 100, 10000);
                    $try->onResolve(static function ($e, $secondaryUnlock) use (&$unlocked, &$secondary) {
                        if ($e) {
                            throw $e;
                        }
                        $unlocked = true;
                        $secondary = $secondaryUnlock;
                    });

                    $this->assertFalse($unlocked, "The lock wasn't acquired");
                } finally {
                    if ($primary) {
                        $primary();
                    }
                }

                yield new Delayed(100 * 2);
                $this->assertTrue($unlocked, "The lock wasn't released");

                yield $try;
                $this->assertInstanceOf(\Closure::class, $secondary);
            } finally {
                if ($secondary) {
                    $secondary();
                }
            }
        });
    }
    public function testSharedLock()
    {
        $this->execute(function () {
            $primary = null;
            $secondary = null;
            try {
                $primary = yield $this->tryLockShared(__FILE__, 100, 100);
                $this->assertInstanceOf(\Closure::class, $primary);
                $secondary = yield $this->tryLockShared(__FILE__, 100, 100);
                $this->assertInstanceOf(\Closure::class, $secondary);
            } finally {
                if ($primary) {
                    $primary();
                }
                if ($secondary) {
                    $secondary();
                }
            }
        });
    }
    public function testClose()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            yield $handle->close();

            $this->expectException(ClosedException::class);
            yield $handle->read();
        });
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToSmallerSize()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");

            $handle->write("foo");
            yield $handle->write("bar");
            yield $handle->truncate(4);
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertTrue($handle->eof());
            $this->assertSame("foob", $contents);

            yield $handle->write("bar");
            $this->assertSame(7, $handle->tell());
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertSame("foobbar", $contents);

            yield $handle->close();
        });
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToLargerSize()
    {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");

            yield $handle->write("foo");
            yield $handle->truncate(6);
            $this->assertSame(3, $handle->tell());
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertTrue($handle->eof());
            $this->assertSame("foo\0\0\0", $contents);

            yield $handle->write("bar");
            $this->assertSame(9, $handle->tell());
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertSame("foo\0\0\0bar", $contents);

            yield $handle->close();
        });
    }
}
