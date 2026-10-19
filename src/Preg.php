<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Upstream's PhpCsFixer\Preg: a pattern runs with /u (PCRE2 UTF + UCP), and without it when the subject is not valid UTF-8.
 * Patterns are written WITHOUT the `u` modifier; it is appended here. The subject is checked once (`//u`) instead of upstream's
 * try-and-fall-back, which is the same thing for every subject but a pathological backtrack (compiler issue #367: a /u call on
 * invalid UTF-8 answers 0 / the subject natively instead of false / null, so the fall-back could not be detected anyway).
 */
final class Preg
{
    public static function utf8(string $s): bool
    {
        return \preg_match('//u', $s) === 1;
    }

    private static function p(string $pattern, string $subject): string
    {
        return self::utf8($subject) ? $pattern . 'u' : $pattern;
    }

    /** @param array<int|string, mixed> $m */
    public static function match(string $pattern, string $subject, ?array &$m = null, int $flags = 0, int $offset = 0): bool
    {
        return \preg_match(self::p($pattern, $subject), $subject, $m, $flags, $offset) === 1;
    }

    /** bool match, no captures */
    public static function test(string $pattern, string $subject): bool
    {
        return \preg_match(self::p($pattern, $subject), $subject) === 1;
    }

    public static function replace(string $pattern, string $to, string $subject, int $limit = -1): string
    {
        return (string) \preg_replace(self::p($pattern, $subject), $to, $subject, $limit);
    }

    public static function replaceCallback(string $pattern, \Closure $cb, string $subject, int $limit = -1): string
    {
        return (string) \preg_replace_callback(self::p($pattern, $subject), $cb, $subject, $limit);
    }

    /** @return string[] */
    public static function split(string $pattern, string $subject, int $limit = -1, int $flags = 0): array
    {
        $r = \preg_split(self::p($pattern, $subject), $subject, $limit, $flags);
        return $r === false ? [] : $r;
    }

    /** @param array<int|string, mixed> $m */
    public static function matchAll(string $pattern, string $subject, ?array &$m = null, int $flags = \PREG_PATTERN_ORDER): int
    {
        $r = \preg_match_all(self::p($pattern, $subject), $subject, $m, $flags);
        return $r === false ? 0 : $r;
    }
}
