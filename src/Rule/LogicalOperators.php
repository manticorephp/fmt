<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/** Parity target: PhpCsFixer\Fixer\Operator\LogicalOperatorsFixer (priority 0, risky). `and` -> `&&`, `or` -> `||` (the kind changes too). Text rule. */
final class LogicalOperators extends TextRule
{
    public function name(): string
    {
        return 'logical_operators';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] === \__McTokId::T_LOGICAL_AND) {
            $s->setKind($i, \__McTokId::T_BOOLEAN_AND);
            return '&&';
        }
        if ($s->kind[$i] === \__McTokId::T_LOGICAL_OR) {
            $s->setKind($i, \__McTokId::T_BOOLEAN_OR);
            return '||';
        }
        return null;
    }
}
