<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\SpacesInsideParenthesesFixer, space=none (priority 3). Inside every plain `(` and
 * class-instantiation `(` (but not `array(`): the blank after `(` goes unless a comment follows it or it holds a line break; the
 * blank before `)` goes unless it holds a line break or the token before is a comma. `space=single` is refused (Rules).
 */
final class SpacesInsideParentheses extends GapRule
{
    public function name(): string
    {
        return 'spaces_inside_parentheses';
    }

    public function priority(): int
    {
        return 3;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [40];
    }

    public function beforeKinds(): array
    {
        return [41];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    private static function isOpen(Source $s, int $j): bool
    {
        $c = $s->ct[$j];
        if (!($c === CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN || ($c === 0 && $s->kind[$j] === 40))) {
            return false;
        }
        return !($j > 0 && $s->ct[$j - 1] === 0 && $s->kind[$j - 1] === \__McTokId::T_ARRAY);
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($gap === '') {
            return $gap;
        }
        if ($i > 0 && self::isOpen($s, $i - 1) && !Gap::hasComment($gap) && \strpos($gap, "\n") === false) {
            $gap = '';
            return $gap;
        }
        if ($i < $s->n && $s->kind[$i] === 41 && ($s->ct[$i] === 0 || $s->ct[$i] === CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE)
            && $i > 0 && !($s->kind[$i - 1] === 44 && $s->ct[$i - 1] === 0) && self::isOpen($s, $s->pair[$i])) {
            if (!Gap::hasComment($gap)) {
                return \strpos($gap, "\n") === false ? '' : $gap;
            }
            $p = Gap::pieces($gap);
            $m = \count($p);
            $z = $p[$m - 1];
            if (Gap::isSpace($z) && \strpos($z, "\n") === false) {
                \array_pop($p);
                return \implode('', $p);
            }
        }
        return $gap;
    }
}
