<?php

namespace Amp\File;

use Amp\{ Coroutine, Promise, Success };
use Amp\Parallel\Worker\{ TaskException, Worker, WorkerException };

class ParallelHandle implements Handle {
    /** @var \Amp\Parallel\Worker\Worker */
    private $worker;

    /** @var int|null */
    private $id;

    /** @var string */
    private $path;

    /** @var int */
    private $position;

    /** @var int */
    private $size;

    /** @var string */
    private $mode;

    /** @var int Number of pending write operations. */
    private $pendingWrites = 0;

    /**
     * @param \Amp\Parallel\Worker\Worker $worker
     * @param int $id
     * @param string $path
     * @param int $size
     * @param string $mode
     */
    public function __construct(Worker $worker, int $id, string $path, int $size, string $mode) {
        $this->worker = $worker;
        $this->id = $id;
        $this->path = $path;
        $this->size = $size;
        $this->mode = $mode;
        $this->position = $this->mode[0] === 'a' ? $this->size : 0;
    }

    public function __destruct() {
        if ($this->id !== null) {
            $this->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function path(): string {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Promise {
        $this->open = false;

        if ($this->worker->isRunning()) {
            $promise = $this->worker->enqueue(new Internal\FileTask('fclose', [], $this->id));
            $this->id = null;
            return $promise;
        }

        return new Success;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool {
        return ($this->pendingWrites > 0) ? false : ($this->size <= $this->position);
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise {
        if ($this->id === null) {
            throw new \Error("The file has been closed");
        }

        return new Coroutine($this->doRead($length));
    }

    private function doRead(int $length): \Generator {
        try {
            $data = yield $this->worker->enqueue(new Internal\FileTask('fread', [$length], $this->id));
        } catch (TaskException $exception) {
            throw new FilesystemException("Reading from the file failed", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Sending the task to the worker failed", $exception);
        }

        $this->position += \strlen($data);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): Promise {
        if ($this->id === null) {
            throw new \Error("The file has been closed");
        }

        return new Coroutine($this->doWrite($data));
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = ""): Promise {
        $promise = $this->write($data);
        $promise->onResolve([$this, "close"]);
        return $promise;
    }

    private function doWrite(string $data): \Generator {
        ++$this->pendingWrites;

        try {
            $length = yield $this->worker->enqueue(new Internal\FileTask('fwrite', [$data], $this->id));
        } catch (TaskException $exception) {
            throw new FilesystemException("Writing to the file failed", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Sending the task to the worker failed", $exception);
        } finally {
            --$this->pendingWrites;
        }

        $this->position += $length;

        return $length;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): Promise {
        if ($this->id === null) {
            throw new \Error("The file has been closed");
        }

        return new Coroutine($this->doSeek($offset, $whence));
    }

    private function doSeek(int $offset, int $whence) {
        switch ($whence) {
            case \SEEK_SET:
            case \SEEK_CUR:
            case \SEEK_END:
                try {
                    $this->position = yield $this->worker->enqueue(
                        new Internal\FileTask('fseek', [$offset, $whence], $this->id)
                    );
                } catch (TaskException $exception) {
                    throw new FilesystemException('Seeking in the file failed.', $exception);
                } catch (WorkerException $exception) {
                    throw new FilesystemException("Sending the task to the worker failed", $exception);
                }

                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }

                return $this->position;

            default:
                throw new \Error('Invalid whence value. Use SEEK_SET, SEEK_CUR, or SEEK_END.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function mode(): string {
        return $this->mode;
    }
}
