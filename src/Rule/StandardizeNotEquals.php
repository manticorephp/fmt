<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/** Parity target: PhpCsFixer\Fixer\Operator\StandardizeNotEqualsFixer (priority 0). `<>` -> `!=`. Text rule. */
final class StandardizeNotEquals extends TextRule
{
    public function name(): string
    {
        return 'standardize_not_equals';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_IS_NOT_EQUAL];
    }

    public function fixText(Source $s, int $i): ?string
    {
        return $s->kind[$i] === \__McTokId::T_IS_NOT_EQUAL && $s->text($i) !== '!=' ? '!=' : null;
    }
}
