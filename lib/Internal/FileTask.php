<?php
namespace Amp\File\Internal;

use Amp\File\{ BlockingDriver, BlockingHandle, FilesystemException };
use Amp\Parallel\Worker\{ Environment, Task };

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
class FileTask extends BlockingDriver implements Task {
    const ENV_PREFIX = self::class . '_';
    
    /** @var string */
    private $operation;

    /** @var mixed[] */
    private $args;

    /**  @var string|null */
    private $id;

    /**
     * @param string $operation
     * @param array $args
     * @param int $id File ID.
     *
     * @throws \Error
     */
    public function __construct(string $operation, array $args = [], int $id = null) {
        if (!\strlen($operation)) {
            throw new \Error('Operation must be a non-empty string');
        }

        $this->operation = $operation;
        $this->args = $args;

        if ($id !== null) {
            $this->id = $this->makeId($id);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Amp\File\FilesystemException
     * @throws \Error
     */
    public function run(Environment $environment) {
        if ('f' === $this->operation[0]) {
            if ("fopen" === $this->operation) {
                $path = $this->args[0];
                $mode = \str_replace(['b', 't'], '', $this->args[1]);
    
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
                        throw new FilesystemException("Invalid file mode");
                }
    
                $handle = @\fopen($path, $mode . 'b');
    
                if (!$handle) {
                    $message = 'Could not open the file.';
                    if ($error = \error_get_last()) {
                        $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                    }
                    throw new FilesystemException($message);
                }
    
                $file = new BlockingHandle($handle, $path, $mode);
                $id = (int) $handle;
                $size = \fstat($handle)["size"];
                $environment->set($this->makeId($id), $file);
                
                return [$id, $size, $mode];
            }

            if (null === $this->id) {
                throw new FilesystemException("No file ID provided");
            }

            if (!$environment->exists($this->id)) {
                throw new FilesystemException("No file handle with the given ID has been opened on the worker");
            }

            /** @var \Amp\File\BlockingHandle $file */
            if (!($file = $environment->get($this->id)) instanceof BlockingHandle) {
                throw new FilesystemException("File storage found in inconsistent state");
            }

            switch ($this->operation) {
                case "fread":
                case "fwrite":
                case "fseek":
                    return ([$file, \substr($this->operation, 1)])(...$this->args);

                case "fclose":
                    $file->close();
                    $environment->delete($this->id);
                    return true;

                default:
                    throw new \Error('Invalid operation');
            }
        }

        switch ($this->operation) {
            case "stat":
            case "unlink":
            case "rename":
            case "link":
            case "symlink":
            case "readlink":
            case "lstat":
            case "exists":
            case "isfile":
            case "isdir":
            case "mkdir":
            case "scandir":
            case "rmdir":
            case "chmod":
            case "chown":
            case "touch":
            case "size":
            case "mtime":
            case "atime":
            case "ctime":
            case "get":
            case "put":
                return ([$this, $this->operation])(...$this->args);

            default:
                throw new \Error("Invalid operation");
        }
    }

    /**
     * @param int $id
     *
     * @return string
     */
    private function makeId(int $id): string {
        return self::ENV_PREFIX . $id;
    }
}
