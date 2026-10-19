<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\AttributeNotation\AttributeBlockNoSpacesFixer (priority 0). The whitespace right behind `#[` and
 * right before its `]` goes unless a comment is part of that stretch.
 */
final class AttributeBlockNoSpaces extends GapRule
{
    public function name(): string
    {
        return 'attribute_block_no_spaces';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [\__McTokId::T_ATTRIBUTE];
    }

    public function beforeKinds(): array
    {
        return [93];
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
        if (($i > 0 && $s->kind[$i - 1] === \__McTokId::T_ATTRIBUTE) || ($i < $s->n && $s->kind[$i] === 93 && $s->ct[$i] === CT::ATTRIBUTE_CLOSE)) {
            return '';
        }
        return $gap;
    }
}
