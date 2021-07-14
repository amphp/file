# file ![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/file` allows non-blocking access to the filesystem for [Amp](https://github.com/amphp/amp).

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/file
```

## Optional Extension Backends

Extensions allow using threading in the background instead of using multiple processes.
 
 - [`ext-eio`](https://pecl.php.net/package/eio)
 - [`ext-uv`](https://github.com/amphp/ext-uv)
 - [`ext-parallel`](https://github.com/krakjoe/parallel)

`amphp/file` works out of the box without any PHP extensions. It uses multi-processing by default, but also comes with a blocking driver that just uses PHP's blocking functions in the current process.

## Versioning

`amphp/file` follows the [semver](https://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
