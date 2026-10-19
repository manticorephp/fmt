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
