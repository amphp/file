# amp/fs [![Build Status](https://travis-ci.org/amphp/fs.svg?branch=master)](https://travis-ci.org/amphp/fs)

`amp/fs` is a non-blocking filesystem manipulation library for use with the
[amp concurrency framework](https://github.com/amphp/amp).

**Dependencies**

- PHP 5.5+
- [php-uv](https://github.com/chobie/php-uv) (optional)
- [eio](https://pecl.php.net/package/eio) (optional)

`amp/fs` works out of the box without any PHP extensions. However, it does so
in a blocking manner. This capability only exists to simplify development across
environments where extensions may not be present. Using this library in
production without either the php-uv or eio extension is **NOT** reccommended.

**Current Version**

 - v0.1.0

**Installation**

```bash
$ composer require amphp/fs: ~0.1
```
