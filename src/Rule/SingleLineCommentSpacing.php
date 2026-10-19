<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\SingleLineCommentSpacingFixer (priority 1). `//x`, `#x` and a single-line `/*x*\/` (not `/**`) get one space
 * after the opener; a block comment one before its closer too. Comments are trivia: this rewrites the pieces of a gap.
 */
final class SingleLineCommentSpacing extends GapRule
{
    public function name(): string
    {
        return 'single_line_comment_spacing';
    }

    public function priority(): int
    {
        return 1;
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

    private static function lead(string $content, string $prefix): string
    {
        $l = \strlen($prefix);
        $b = $l < \strlen($content) ? \ord($content[$l]) : 0;
        if ($b === 32 || $b === 9 || ($b >= 0xA0 && Trivia::hLen($content, $l, \preg_match('//u', $content) === 1) > 0)) {
            return $content; // `^prefix\h+.*$`: a horizontal blank, then anything on the line
        }
        return \substr($content, 0, $l) . ' ' . \substr($content, $l);
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (!Gap::hasComment($gap)) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $changed = false;
        foreach ($p as $k => $c) {
            if (Gap::isSpace($c)) {
                continue;
            }
            $len = \strlen($c);
            if ($c[0] === '/') {
                if ($len < 3) {
                    continue;
                }
                if ($c[1] === '*') {
                    if ($len < 5 || $c[2] === '*' || \strpos($c, "\n") !== false) {
                        continue;
                    }
                    $new = self::lead(\rtrim(\substr($c, 0, -2)) . ' ' . \substr($c, -2), '/*');
                } else {
                    $new = self::lead($c, '//');
                }
            } else {
                if ($len < 2 || $c[1] === '[') {
                    continue;
                }
                $new = self::lead($c, '#');
            }
            if ($new !== $c) {
                $p[$k] = $new;
                $changed = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
