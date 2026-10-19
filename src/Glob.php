<?php

declare(strict_types=1);

namespace Fmt;

/** Finder-style patterns: `/regex/`, a glob (`*` `?` `[..]` `**`) matched against the whole subject, or, for a path pattern, a substring. */
final class Glob
{
    /** @var array<string, string> */
    private static array $cache = [];

    /** A regex (with delimiters) for a pattern; globs are anchored, `*` stops at `/` and `**` does not. */
    public static function regex(string $p): string
    {
        $hit = self::$cache[$p] ?? null;
        if ($hit !== null) {
            return $hit;
        }
        $n = \strlen($p);
        if ($n > 2 && $p[0] === '/' && $p[$n - 1] === '/') {
            return self::$cache[$p] = $p;
        }
        $re = '';
        for ($i = 0; $i < $n; $i++) {
            $c = $p[$i];
            if ($c === '*') {
                if ($i + 1 < $n && $p[$i + 1] === '*') {
                    $i++;
                    if ($i + 1 < $n && $p[$i + 1] === '/') {
                        $re .= '(?:.*/)?'; // `**/` also matches nothing
                        $i++;
                    } else {
                        $re .= '.*';
                    }
                } else {
                    $re .= '[^/]*';
                }
            } elseif ($c === '?') {
                $re .= '[^/]';
            } elseif ($c === '[') {
                $e = \strpos($p, ']', $i + 2);
                if ($e === false) {
                    $re .= '\[';
                } else {
                    $cls = \substr($p, $i + 1, $e - $i - 1);
                    if ($cls[0] === '!') {
                        $cls = '^' . \substr($cls, 1);
                    }
                    $re .= '[' . \str_replace('\\', '\\\\', $cls) . ']';
                    $i = $e;
                }
            } elseif ($c === '{') {
                $e = \strpos($p, '}', $i);
                if ($e === false) {
                    $re .= '\{';
                } else {
                    $alts = [];
                    foreach (\explode(',', \substr($p, $i + 1, $e - $i - 1)) as $a) {
                        $alts[] = \preg_quote($a, '~');
                    }
                    $re .= '(?:' . \implode('|', $alts) . ')';
                    $i = $e;
                }
            } else {
                $re .= \preg_quote($c, '~');
            }
        }
        return self::$cache[$p] = '~^' . $re . '$~s';
    }

    public static function isGlob(string $p): bool
    {
        return \strpbrk($p, '*?[{') !== false || ($p !== '' && $p[0] === '/' && \strlen($p) > 2 && $p[\strlen($p) - 1] === '/');
    }

    public static function matches(string $pattern, string $subject): bool
    {
        return \preg_match(self::regex($pattern), $subject) === 1;
    }

    /** A path pattern: a glob / regex matches the whole relative path, a plain string matches anywhere inside it. */
    public static function pathMatches(string $pattern, string $subject): bool
    {
        if (self::isGlob($pattern)) {
            return self::matches($pattern, $subject);
        }
        return \str_contains($subject, \trim($pattern, '/'));
    }
}
