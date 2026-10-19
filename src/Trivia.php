<?php

declare(strict_types=1);

namespace Fmt;

/** The trivia of a gap the way upstream's token list holds it: whitespace and comment pieces, plus the Tokens::remove*Whitespace helpers. */
final class Trivia
{
    /** @return string[] [] for an empty gap */
    public static function pieces(string $g): array
    {
        if ($g === '') {
            return [];
        }
        return Gap::hasComment($g) ? Gap::pieces($g) : [$g];
    }

    /** Token::isWhitespace(" \t"): only blanks (an empty, cleared piece counts) */
    public static function blank(string $p): bool
    {
        return Gap::isSpace($p) && \strspn($p, " \t") === \strlen($p);
    }

    /** preg_match('/^\\h+$/', $p): blanks, plus one line feed at the very end (`$` allows it) */
    public static function hspace(string $p): bool
    {
        $l = \strspn($p, " \t");
        $n = \strlen($p);
        return $l > 0 && ($l === $n || ($l + 1 === $n && $p[$l] === "\n"));
    }

    /**
     * Bytes of the PCRE `\h` character at offset $at of $s, 0 when there is none. Preg::match tries /u first: then \h is also U+00A0, U+1680,
     * U+180E, U+2000..U+200A, U+202F, U+205F, U+3000; the byte fallback (invalid UTF-8) knows 0xA0.
     */
    public static function hLen(string $s, int $at, bool $utf8): int
    {
        if ($at >= \strlen($s)) {
            return 0;
        }
        $c = \ord($s[$at]);
        if ($c === 32 || $c === 9) {
            return 1;
        }
        if (!$utf8) {
            return $c === 0xA0 ? 1 : 0;
        }
        if ($c === 0xC2 && $at + 1 < \strlen($s) && \ord($s[$at + 1]) === 0xA0) {
            return 2;
        }
        if ($c >= 0xE1 && $at + 2 < \strlen($s)) {
            $u = \substr($s, $at, 3);
            if ($u === "\xe1\x9a\x80" || $u === "\xe1\xa0\x8e" || ($u >= "\xe2\x80\x80" && $u <= "\xe2\x80\x8a") || $u === "\xe2\x80\xaf" || $u === "\xe2\x81\x9f" || $u === "\xe3\x80\x80") {
                return 3;
            }
        }
        return 0;
    }

    /**
     * Preg::match: the pattern (delimiters and flags, no `u`) with `u`, without it for invalid UTF-8. Zend answers false for invalid UTF-8 under `u`; the
     * native preg_match answers 0 with no error (compiler issue, see docs/design.md), so the subject is checked first.
     */
    public static function pmatch(string $re, string $subject): bool
    {
        return \preg_match($re . (\preg_match('//u', $subject) === 1 ? 'u' : ''), $subject) === 1;
    }

    /** Preg::replace, same check */
    public static function preplace(string $re, string $to, string $subject): string
    {
        $r = \preg_replace($re . (\preg_match('//u', $subject) === 1 ? 'u' : ''), $to, $subject);
        return $r ?? $subject;
    }

    /** a `/** ` PHPDoc: T_DOC_COMMENT is `/**` followed by whitespace (anything else starting with `/*` is a T_COMMENT) */
    public static function isDoc(string $p): bool
    {
        return \strlen($p) >= 4 && $p[0] === '/' && $p[1] === '*' && $p[2] === '*' && ($p[3] === ' ' || $p[3] === "\t" || $p[3] === "\n" || $p[3] === "\r");
    }

    public static function isComment(string $p): bool
    {
        return !Gap::isSpace($p);
    }

    /**
     * Tokens::removeLeadingWhitespace for the token behind gap $g: the whitespace token right before it goes. Right behind a `//` / `#`
     * comment only what follows the comment's own line break goes (upstream leaves the file untouched when no line break leads).
     */
    public static function removeLeading(string $g): string
    {
        if ($g === '') {
            return $g;
        }
        $p = self::pieces($g);
        $m = \count($p);
        if (!Gap::isSpace($p[$m - 1])) {
            return $g;
        }
        if ($m >= 2 && !Gap::isSpace($p[$m - 2]) && !\str_starts_with($p[$m - 2], '/*')) {
            if (\preg_match('/^(\r\n|\n|\r|\x0b|\x0c)(.+)$/s', $p[$m - 1], $mm) !== 1) {
                return $g;
            }
            $p[$m - 1] = $mm[1];
        } else {
            \array_pop($p);
        }
        return \implode('', $p);
    }

    /** Tokens::removeTrailingWhitespace for the token before gap $g: its first piece goes when it is whitespace. */
    public static function removeTrailing(string $g): string
    {
        if ($g === '') {
            return $g;
        }
        $p = self::pieces($g);
        if (!Gap::isSpace($p[0])) {
            return $g;
        }
        \array_shift($p);
        return \implode('', $p);
    }
}
