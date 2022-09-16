# amphp/file

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/file` provides non-blocking file system access.

[![Latest Release](https://img.shields.io/github/release/amphp/file.svg?style=flat-square)](https://github.com/amphp/file/releases)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://github.com/amphp/file/blob/master/LICENSE)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/file
```

`amphp/file` works out of the box without any PHP extensions.
It uses multiple processes by default, but also comes with a blocking driver that just uses PHP's blocking functions in the current process.

Extensions allow using threading in the background instead of using multiple processes:

- [`ext-eio`](https://pecl.php.net/package/eio)
- [`ext-uv`](https://github.com/amphp/ext-uv)
- [`ext-parallel`](https://github.com/krakjoe/parallel)

## Usage

### read

Read the specified file's contents:

```php
$contents = Amp\File\read('/path/to/file.txt');
```

### write

Write the contents string to the specified path:

```php
Amp\File\write('/path/to/file.txt', 'contents');
```

### openFile

Open a [`File` handle](#file) for the specified path, e.g. to stream data:

```php
$file = Amp\File\openFile('/path/to/file.txt', 'r');
Amp\ByteStream\pipe($file, getStdout());
```

### getStatus

Execute a file stat operation.

If the requested path does not exist the function will return `null`.

### getLinkStatus

Same as [`getStatus()`](#getstatus) except if the path is a link then the link's data is returned.

If the requested path does not exist the function will return `null`.

### exists

Checks whether the specified path exists.

### getSize

Returns the file size in bytes.

### isDirectory

Checks whether the given path exists and is a directory.

### isFile

Checks whether the given path exists and is a regular file.

### isSymlink

Checks whether the given path exists and is a symlink.

### getModificationTime

Returns the file's modification time as Unix timestamp in seconds.

### getAccessTime

Returns the file's access time as Unix timestamp in seconds.

### getCreationTime

Returns the file's creation time as Unix timestamp in seconds.

### createHardlink

Creates a new hardlink.

### createSymlink

Creates a new symlink.

### resolveSymlink

Resolves a symlink to its target path.

### move

Move / rename a file or directory.

### deleteFile

Deletes a file.

### createDirectory

Creates a directory.

### createDirectoriesRecursively

Creates a directory and its parents.

### deleteDirectory

Deletes a directory.

### listFiles

List all files and subdirectories in a directory.

### changePermissions

Change the permissions of a file or directory.

### changeOwner

Change the ownership of a file or directory.

### touch

Update the access and modification time of the specified path.

If the file does not exist it will be created automatically.

### File

Reference to an open file handle.

#### File::read

See also `File::isReadable`.

#### File::write

See also `File::isWritable`.

#### File::end

See `WritableStream::end()`.

#### File::close

Close the file handle.

 - `File::isClosed()` can be used to check if the file handle has already been closed. 
 - `File::onClose()` can be used ot register a callback that's called on file handle closure.

#### File::seek

Set the internal pointer position.

 - `SEEK_SET`: Set position equal to offset bytes.
 - `SEEK_CUR`: Set position to current location plus offset.
 - `SEEK_END`: Set position to end-of-file plus offset.

Returns the new offset position.

See also `File::isSeekable`.

#### File::tell

Return the current internal offset position of the file handle.

#### File::eof

Test for being at the end of the stream (a.k.a. "end-of-file").

#### File::getPath

Retrieve the path used when opening the file handle.

#### File::getMode

Retrieve the mode used when opening the file handle.

#### File::truncate

Truncates the file to the given length.
If `$size` is larger than the current file size, the file is extended with NUL bytes.

## Versioning

`amphp/file` follows the [semver](https://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
