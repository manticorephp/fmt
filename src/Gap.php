<?php

declare(strict_types=1);

namespace Fmt;

/** Helpers over a gap string (whitespace and comments between two tokens). */
final class Gap
{
    // Per-gap classification, computed once per gap (Source::parse, and again by Source::setGap on a rewrite).
    // Rules/Engine test these in O(1) instead of re-scanning the string. A gap with flags 0 is empty or only blanks
    // (spaces/tabs) and no rule has anything to do with it.
    /** the gap holds a "\n" */
    public const G_NL = 1;
    /** the gap holds a "\r" */
    public const G_CR = 2;
    /** the gap holds a comment (`/` or `#`): the string is not pure whitespace, Gap::pieces is needed */
    public const G_COMMENT = 4;
    /** the gap holds \x0B \x0C \0, U+0085/U+2028/U+2029 as UTF-8, or a lone 0x85 in invalid UTF-8 (PCRE \R candidates beyond \r \n, plus upstream's rtrim set); only set together with G_COMMENT */
    public const G_ODD = 8;
    /** pure whitespace that starts with spaces/tabs directly followed by a line break (trailing whitespace of the previous line) */
    public const G_LEADWS_NL = 16;
    /** two or more "\n" (a blank line) */
    public const G_NL2 = 32;

    public static function flags(string $g): int
    {
        $n = \strlen($g);
        if ($n === 0) {
            return 0;
        }
        $f = 0;
        if (\strspn($g, " \t\r\n") === $n) {
            $a = \strspn($g, " \t");
            if ($a === $n) {
                return 0;
            }
            if ($a > 0) {
                $f = self::G_LEADWS_NL;
            }
        } else {
            $f = self::G_COMMENT;
            $odd = \strpbrk($g, "\x0b\x0c\0") !== false || \str_contains($g, "\xc2\x85")
                || \str_contains($g, "\xe2\x80\xa8") || \str_contains($g, "\xe2\x80\xa9");
            if (!$odd && \strpos($g, "\x85") !== false) {
                $odd = \preg_match('//u', $g) !== 1; // a lone 0x85 is a line break only in invalid UTF-8
            }
            if ($odd) {
                $f |= self::G_ODD;
            }
        }
        if (\strpos($g, "\r") !== false) {
            $f |= self::G_CR;
        }
        $nl = \substr_count($g, "\n");
        if ($nl > 0) {
            $f |= self::G_NL;
            if ($nl > 1) {
                $f |= self::G_NL2;
            }
        }
        return $f;
    }

    /**
     * Split into the trivia tokens php-cs-fixer sees: whitespace runs, `//`/`#` comments (up to, not including, the
     * line break) and block comments. Mirrors its WhitespacyCommentTransformer: a comment is rtrim()med (" \t\n\r\0\x0B")
     * and the cut-off whitespace is prepended to the following whitespace token, or becomes a token of its own.
     * A piece is a comment iff it starts with `#` or `/`.
     * @return string[]
     */
    public static function pieces(string $g): array
    {
        $out = [];
        $n = \strlen($g);
        $i = 0;
        $pending = '';
        while ($i < $n) {
            $c = \ord($g[$i]);
            $j = $i;
            if ($c === 35 || ($c === 47 && $i + 1 < $n && \ord($g[$i + 1]) === 47)) {
                while ($j < $n) {
                    $d = \ord($g[$j]);
                    if ($d === 10 || $d === 13) {
                        break;
                    }
                    $j++;
                }
            } elseif ($c === 47) {
                $e = \strpos($g, '*/', $i + 2);
                $j = $e === false ? $n : $e + 2;
            } else {
                while ($j < $n) {
                    $d = \ord($g[$j]);
                    if ($d === 35 || $d === 47) {
                        break;
                    }
                    $j++;
                }
                $out[] = $pending . \substr($g, $i, $j - $i);
                $pending = '';
                $i = $j;
                continue;
            }
            $piece = \substr($g, $i, $j - $i);
            $t = \rtrim($piece, " \t\n\r\0\x0B");
            if ($pending !== '') {
                $out[] = $pending;
                $pending = '';
            }
            $out[] = $t;
            if (\strlen($t) < \strlen($piece)) {
                $pending = \substr($piece, \strlen($t));
            }
            $i = $j;
        }
        if ($pending !== '') {
            $out[] = $pending;
        }
        return $out;
    }

    public static function hasComment(string $g): bool
    {
        return \strpbrk($g, '/#') !== false;
    }

    public static function isSpace(string $piece): bool
    {
        $c = \ord($piece[0]);
        return $c !== 35 && $c !== 47;
    }

    /** Length of the \R (no /u; trivia is ASCII) at $i, 0 when there is none. */
    public static function rLen(string $s, int $i): int
    {
        $c = \ord($s[$i]);
        if ($c === 13) {
            return ($i + 1 < \strlen($s) && \ord($s[$i + 1]) === 10) ? 2 : 1;
        }
        return ($c === 10 || $c === 11 || $c === 12) ? 1 : 0;
    }

    /**
     * Upstream: Preg::replace('#\R#', $to, $s), which first tries the pattern with /u and, only when the subject is
     * not valid UTF-8, without it. \R = \r\n | \n | \r | \x0b | \x0c, plus under /u U+0085 (C2 85), U+2028, U+2029
     * (E2 80 A8/A9), plus without /u the lone byte 0x85 (which is also a UTF-8 continuation byte, e.g. in a star).
     */
    public static function replaceR(string $s, string $to): string
    {
        $lf = $to === "\n";
        if (\strpbrk($s, $lf ? "\r\x0b\x0c\x85\xe2" : "\r\n\x0b\x0c\x85\xe2") === false) {
            return $s;
        }
        $utf = true;
        if (\strpbrk($s, "\x85") !== false || \str_contains($s, "\xe2\x80\xa8") || \str_contains($s, "\xe2\x80\xa9")) {
            $utf = \preg_match('//u', $s) === 1;
        }
        $out = '';
        $n = \strlen($s);
        $from = 0;
        $i = 0;
        while ($i < $n) {
            $c = \ord($s[$i]);
            $len = 0;
            if ($c === 10 || $c === 11 || $c === 12) {
                if ($c === 10 && $lf) {
                    $i++;
                    continue;
                }
                $len = 1;
            } elseif ($c === 13) {
                $len = ($i + 1 < $n && \ord($s[$i + 1]) === 10) ? 2 : 1;
            } elseif ($c === 133 && !$utf) {
                $len = 1;
            } elseif ($utf && $c === 194 && $i + 1 < $n && \ord($s[$i + 1]) === 133) {
                $len = 2;
            } elseif ($utf && $c === 226 && $i + 2 < $n && \ord($s[$i + 1]) === 128 && (\ord($s[$i + 2]) === 168 || \ord($s[$i + 2]) === 169)) {
                $len = 3;
            }
            if ($len === 0) {
                $i++;
                continue;
            }
            $out .= \substr($s, $from, $i - $from);
            $out .= $to;
            $i += $len;
            $from = $i;
        }
        $out .= \substr($s, $from);
        return $out;
    }

    /** True when every whitespace piece of the gap is only [ \t\r\n\0\x0B] (a sanity check on the lexer, for tests). */
    public static function isTrivia(string $g): bool
    {
        foreach (self::pieces($g) as $p) {
            if ($p !== '' && self::isSpace($p) && \strspn($p, " \t\r\n\0\x0B") !== \strlen($p)) {
                return false;
            }
        }
        return true;
    }
}
