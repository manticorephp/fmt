<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\NoSpaceAroundDoubleColonFixer (priority 1). The whitespace token on either side of `::`
 * goes unless a comment sits on that side (the sibling past the blank is then a comment), i.e. a whitespace-only gap is emptied.
 */
final class NoSpaceAroundDoubleColon extends GapRule
{
    public function name(): string
    {
        return 'no_space_around_double_colon';
    }

    public function priority(): int
    {
        return 1;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [\__McTokId::T_DOUBLE_COLON];
    }

    public function beforeKinds(): array
    {
        return [\__McTokId::T_DOUBLE_COLON];
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
        if ($gap === '' || Gap::hasComment($gap)) {
            return $gap;
        }
        if (($i < $s->n && $s->kind[$i] === \__McTokId::T_DOUBLE_COLON) || ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_DOUBLE_COLON)) {
            return '';
        }
        return $gap;
    }
}
