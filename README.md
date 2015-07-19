# amp/fs

[![Build Status](https://travis-ci.org/amphp/fs.svg?branch=master)](https://travis-ci.org/amphp/fs)
[![Coverage Status](https://coveralls.io/repos/amphp/fs/badge.svg?branch=master&service=github)](https://coveralls.io/github/amphp/fs?branch=master)
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
> Using this library in production without pecl/eio or php-uv is **NOT** recommended.

**Current Version**

`amp/fs` is currently pre-alpha software and has no tagged releases. Your mileage may vary.

**Installation**

```bash
$ composer require amphp/fs
```
