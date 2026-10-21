<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/** Parity target: PhpCsFixer\Fixer\Strict\StrictComparisonFixer (priority 38, risky). `==` -> `===`, `!=` / `<>` -> `!==` (the kind changes too). Text rule. */
final class StrictComparison extends TextRule
{
    public function name(): string
    {
        return 'strict_comparison';
    }

    public function priority(): int
    {
        return 38;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_IS_EQUAL, \__McTokId::T_IS_NOT_EQUAL];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] === \__McTokId::T_IS_EQUAL) {
            $s->setKind($i, \__McTokId::T_IS_IDENTICAL);
            return '===';
        }
        if ($s->kind[$i] === \__McTokId::T_IS_NOT_EQUAL) {
            $s->setKind($i, \__McTokId::T_IS_NOT_IDENTICAL);
            return '!==';
        }
        return null;
    }
}
