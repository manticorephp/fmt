<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\NoMultilineWhitespaceAroundDoubleArrowFixer (priority 31). A whitespace token next to `=>`
 * that is not only blanks becomes one space: before it unless a `//` / `#` comment precedes, after it unless a comment follows.
 */
final class NoMultilineWhitespaceAroundDoubleArrow extends GapRule
{
    public function name(): string
    {
        return 'no_multiline_whitespace_around_double_arrow';
    }

    public function priority(): int
    {
        return 31;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function beforeKinds(): array
    {
        return [\__McTokId::T_DOUBLE_ARROW];
    }

    public function afterKinds(): array
    {
        return [\__McTokId::T_DOUBLE_ARROW];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    private static function multi(string $ws): bool
    {
        return \strspn($ws, " \t") !== \strlen($ws);
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '') {
            return $gap;
        }
        if ($i < $s->n && $s->kind[$i] === \__McTokId::T_DOUBLE_ARROW) {
            $p = Trivia::pieces($gap);
            $m = \count($p);
            if (!Trivia::isComment($p[$m - 1]) && self::multi($p[$m - 1]) && ($m < 2 || !Trivia::isComment($p[$m - 2]) || \str_starts_with($p[$m - 2], '/*'))) {
                $p[$m - 1] = ' ';
                $gap = \implode('', $p);
            }
        }
        if ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_DOUBLE_ARROW) {
            $p = Trivia::pieces($gap);
            if (!Trivia::isComment($p[0]) && self::multi($p[0]) && (\count($p) < 2 || !Trivia::isComment($p[1]))) {
                $p[0] = ' ';
                $gap = \implode('', $p);
            }
        }
        return $gap;
    }
}
