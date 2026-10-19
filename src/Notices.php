<?php

declare(strict_types=1);

namespace Fmt;

/** Deprecation notices collected while resolving rules; printed once, as upstream does, at the end of a run. */
final class Notices
{
    /** @var string[] */
    private static array $list = [];

    public static function add(string $msg): void
    {
        if (!\in_array($msg, self::$list, true)) {
            self::$list[] = $msg;
        }
    }

    /** @return string[] */
    public static function all(): array
    {
        return self::$list;
    }

    public static function clear(): void
    {
        self::$list = [];
    }

    public static function flush(): void
    {
        if (self::$list !== []) {
            \fwrite(\STDERR, "Detected deprecations in use (they will stop working in next major release):\n");
            foreach (self::$list as $m) {
                \fwrite(\STDERR, '- ' . $m . "\n");
            }
        }
        self::$list = [];
    }
}
