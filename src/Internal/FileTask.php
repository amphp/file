<?php

namespace Amp\File\Internal;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\File\Driver\BlockingDriver;
use Amp\File\Driver\BlockingFile;
use Amp\File\FilesystemException;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Worker\Task;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final class FileTask implements Task
{
    private const ENV_PREFIX = "amphp/file#";

    private static function makeId(int $id): string
    {
        return self::ENV_PREFIX . $id;
    }

    private string $operation;

    private array $args;

    private ?int $id;

    /**
     * @param string $operation
     * @param array $args
     * @param int|null $id File ID.
     *
     * @throws \Error
     */
    public function __construct(string $operation, array $args = [], ?int $id = null)
    {
        if ($operation === '') {
            throw new \Error('Operation must be a non-empty string');
        }

        $this->operation = $operation;
        $this->args = $args;
        $this->id = $id;
    }

    /**
     * @throws FilesystemException
     * @throws \Amp\Cache\CacheException
     * @throws \Amp\ByteStream\ClosedException
     * @throws \Amp\ByteStream\StreamException
     */
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
    {
        if ('f' === $this->operation[0]) {
            if ("fopen" === $this->operation) {
                $path = $this->args[0];
                $mode = \str_replace(['b', 't', 'e'], '', $this->args[1]);

                switch ($mode) {
                    case "r":
                    case "r+":
                    case "w":
                    case "w+":
                    case "a":
                    case "a+":
                    case "x":
                    case "x+":
                    case "c":
                    case "c+":
                        break;

                    default:
                        throw new \Error("Invalid file mode");
                }

                $handle = @\fopen($path, $mode . 'be');

                if (!$handle) {
                    $message = 'Could not open the file.';
                    if ($error = \error_get_last()) {
                        $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                    }
                    throw new FilesystemException($message);
                }

                $file = new BlockingFile($handle, $path, $mode);
                $id = (int) $handle;
                $size = \fstat($handle)["size"];
                $cache->set(self::makeId($id), $file);

                return [$id, $size, $mode];
            }

            if ($this->id === null) {
                throw new FilesystemException("No file ID provided");
            }

            $id = self::makeId($this->id);

            $file = $cache->get($id);
            if ($file === null) {
                throw new FilesystemException(\sprintf(
                    "No file handle with the ID %d has been opened on the worker",
                    $this->id
                ));
            }

            if (!$file instanceof BlockingFile) {
                throw new FilesystemException("File storage found in inconsistent state");
            }

            switch ($this->operation) {
                case "fread":
                    \array_shift($this->args);
                    return $file->read($cancellation, ...$this->args);
                case "fwrite":
                    return $file->write(...$this->args);
                case "fseek":
                    return $file->seek(...$this->args);
                case "ftruncate":
                    return $file->truncate(...$this->args);

                case "fclose":
                    $cache->delete($id);
                    $file->close();
                    return null;

                default:
                    throw new \Error('Invalid operation');
            }
        }

        switch ($this->operation) {
            case "getStatus":
            case "deleteFile":
            case "move":
            case "createHardlink":
            case "createSymlink":
            case "resolveSymlink":
            case "getLinkStatus":
            case "exists":
            case "createDirectory":
            case "createDirectoryRecursively":
            case "listFiles":
            case "deleteDirectory":
            case "changePermissions":
            case "changeOwner":
            case "touch":
            case "read":
            case "write":
                return ([new BlockingDriver, $this->operation])(...$this->args);

            default:
                throw new \Error("Invalid operation - " . $this->operation);
        }
    }
}
