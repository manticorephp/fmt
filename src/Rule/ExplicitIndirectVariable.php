<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\ExplicitIndirectVariableFixer (priority 0). `$$foo` -> `${$foo}`, `$a->$b` -> `$a->{$b}`: a variable right after `$`
 * or an object operator gets braces (the re-lex gives the brace tokens their dynamic-variable / dynamic-property kinds).
 */
final class ExplicitIndirectVariable extends EditRule
{
    public function name(): string
    {
        return 'explicit_indirect_variable';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_VARIABLE) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 1; $i > 0; --$i) {
            if ($s->kind[$i] !== \__McTokId::T_VARIABLE || $s->ct[$i] !== 0) {
                continue;
            }
            $p = $i - 1;
            $pk = $s->kind[$p];
            $dollar = $pk === 36 && $s->ct[$p] === 0;
            if (!$dollar && !(($pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) && $s->ct[$p] === 0)) {
                continue;
            }
            $s->edit(Source::tokAt($i), Source::tokAt($i) + 1, '{' . $s->text($i) . '}');
        }
    }
}
