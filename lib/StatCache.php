<?php

namespace Amp\File;

class StatCache {
    private static $cache = [];
    private static $timeouts = [];
    private static $ttl = 3;
    private static $now = null;
    private static $isInitialized = false;

    private static function init() {
        self::$isInitialized = true;
        self::$now = \time();
        \Amp\repeat(function () {
            self::$now = $now = \time();
            foreach (self::$cache as $path => $expiry) {
                if ($now > $expiry) {
                    unset(
                        self::$cache[$path],
                        self::$timeouts[$path]
                    );
                } else {
                    break;
                }
            }
        }, 1000, ["keep_alive" => false]);
    }

    public static function get($path) {
        return isset(self::$cache[$path]) ? self::$cache[$path] : null;
    }

    public static function set($path, array $stat) {
        if (self::$ttl <= 0) {
            return;
        }
        if (empty(self::$isInitialized)) {
            self::init();
        }
        self::$cache[$path] = $stat;
        self::$timeouts[$path] = self::$now + self::$ttl;
    }

    public static function ttl($seconds) {
        self::$ttl = (int) $seconds;
    }

    public static function clear($path = null) {
        if (isset($path)) {
            unset(
                self::$cache[$path],
                self::$timeouts[$path]
            );
        } else {
            self::$cache = [];
            self::$timeouts = [];
        }
    }
}
