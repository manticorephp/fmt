<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\NoTrailingWhitespaceInCommentFixer (priority 0). A `/* *\/` comment loses its horizontal
 * whitespace before every line end (`/(*ANY)[\h]+$/m`, the pattern tried with /u first as Preg does); behind a `//` / `#` comment
 * the whitespace token loses its leading blanks (the comment token itself is rtrimmed by the lexer, so these are its trailing blanks).
 */
final class NoTrailingWhitespaceInComment extends GapRule
{
    public function name(): string
    {
        return 'no_trailing_whitespace_in_comment';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return Gap::G_COMMENT;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (!Gap::hasComment($gap)) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $m = \count($p);
        $out = '';
        $line = false;
        for ($k = 0; $k < $m; $k++) {
            $x = $p[$k];
            if (!Gap::isSpace($x)) {
                $line = $x[0] === '#' || $x[1] === '/';
                $out .= $line ? $x : self::trimBlock($x);
                continue;
            }
            if ($line) {
                $x = \ltrim($x, " \t");
            }
            $line = false;
            $out .= $x;
        }
        return $out;
    }

    private static function trimBlock(string $c): string
    {
        if (\strpbrk($c, " \t\xc2\xe1\xe2\xe3") === false) {
            return $c;
        }
        $r = \preg_replace('/(*ANY)[\h]+$/mu', '', $c);
        if ($r === null || \preg_last_error() !== \PREG_NO_ERROR) {
            $r = \preg_replace('/(*ANY)[\h]+$/m', '', $c);
        }
        return (string) $r;
    }
}
