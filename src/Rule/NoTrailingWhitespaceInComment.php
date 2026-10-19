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
        $fl = $s->gapFlags[$i];
        if (($fl & Gap::G_COMMENT) === 0) {
            return $gap;
        }
        // nothing to trim without a blank before a line break (G_BLANK_BREAK), an odd line break (G_ODD), a blank run at the very end that
        // does not start a line (a line comment's trailing blanks before a close tag or the end of the file) or a unicode \h
        if (($fl & (Gap::G_BLANK_BREAK | Gap::G_ODD)) === 0) {
            $e = \strlen($gap);
            while ($e > 0 && ($gap[$e - 1] === ' ' || $gap[$e - 1] === "\t")) {
                $e--;
            }
            $tail = $e < \strlen($gap) && $e > 0 && $gap[$e - 1] !== "\n" && $gap[$e - 1] !== "\r";
            if (!$tail && (\strpbrk($gap, "\xc2\xe1\xe2\xe3") === false
                || \preg_match('/\xc2\xa0|\xe1\x9a\x80|\xe1\xa0\x8e|\xe2\x80[\x80-\x8a\xaf]|\xe2\x81\x9f|\xe3\x80\x80/', $gap) !== 1)) {
                return $gap;
            }
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
