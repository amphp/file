<?php

namespace Amp\File\Test;

final class Fixture {
    private static $fixtureId;

    public static function path() {
        if (empty(self::$fixtureId)) {
            self::$fixtureId = \uniqid();
        }

        return \sys_get_temp_dir() . "/amphp_file_fixture/" . __CLASS__ . self::$fixtureId;
    }

    public static function init() {
        $fixtureDir = self::path();
        self::clear();
        if (!\mkdir($fixtureDir, $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory: {$fixtureDir}"
            );
        }
        if (!\mkdir($fixtureDir . "/dir", $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory"
            );
        }
        if (!\file_put_contents($fixtureDir . "/small.txt", "small")) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture file"
            );
        }
    }

    public static function clear() {
        $fixtureDir = self::path();
        if (!\file_exists($fixtureDir)) {
            return;
        }
        if (\stripos(\PHP_OS, "win") === 0) {
            \system('rd /Q /S "' . $fixtureDir . '"');
        } else {
            \system('/bin/rm -rf ' . \escapeshellarg($fixtureDir));
        }
    }
}
