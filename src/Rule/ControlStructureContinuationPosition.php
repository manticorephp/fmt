<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer, position=same_line (priority 0).
 * `else` / `elseif` / `catch` / `finally` / `do {} while` right behind a `}` (only whitespace between) get exactly one space before
 * them, a line break included. `next_line` is refused (Rules).
 */
final class ControlStructureContinuationPosition extends GapRule
{
    public function name(): string
    {
        return 'control_structure_continuation_position';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    /** @return int[] */
    public function beforeKinds(): array
    {
        return [\__McTokId::T_ELSE, \__McTokId::T_ELSEIF, \__McTokId::T_CATCH, \__McTokId::T_FINALLY, \__McTokId::T_WHILE];
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
        if ($gap === ' ' || $i === 0 || $i >= $s->n || $s->ct[$i] !== 0 || $s->kind[$i - 1] !== 125 || $s->ct[$i - 1] !== 0 || Gap::hasComment($gap)) {
            return $gap;
        }
        $k = $s->kind[$i];
        if ($k !== \__McTokId::T_ELSE && $k !== \__McTokId::T_ELSEIF && $k !== \__McTokId::T_CATCH && $k !== \__McTokId::T_FINALLY && $k !== \__McTokId::T_WHILE) {
            return $gap;
        }
        if ($k === \__McTokId::T_WHILE) {
            $o = $s->pair[$i - 1] - 1;
            if ($o < 0 || $s->kind[$o] !== \__McTokId::T_DO || $s->ct[$o] !== 0) {
                return $gap;
            }
        }
        return ' ';
    }
}
