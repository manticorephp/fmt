<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\NoAliasLanguageConstructCallFixer (priority 0). `die` -> `exit`; an `EXIT` that is already
 * `exit` in any case is left (upstream only compares the lowercase). Text rule.
 */
final class NoAliasLanguageConstructCall extends TextRule
{
    public function name(): string
    {
        return 'no_alias_language_construct_call';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_EXIT];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_EXIT) {
            return null;
        }
        return \strtolower($s->text($i)) === 'exit' ? null : 'exit';
    }
}
