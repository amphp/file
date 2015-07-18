# amp/fs

[![Unstable](https://travis-ci.org/amphp/fs.svg?branch=master)](https://travis-ci.org/amphp/fs)
![Unstable](https://img.shields.io/badge/pre_alpha-unstable-orange.svg)

`amp/fs` is a non-blocking filesystem manipulation library for use with the
[amp concurrency framework](https://github.com/amphp/amp).

**Dependencies**

- PHP 5.5+
- [eio](https://pecl.php.net/package/eio)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental, requires PHP7)

`amp/fs` works out of the box without any PHP extensions. However, it does so
in a blocking manner. This capability only exists to simplify development across
environments where extensions may not be present.

> **NOTE**
> Using this library in production without either the eio or php-uv extensions is **NOT** recommended.

**Current Version**

`amp/fs` is currently pre-alpha software and has no tagged releases. Your mileage may vary.

**Installation**

```bash
$ composer require amphp/fs
```
