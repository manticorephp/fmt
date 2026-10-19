<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\SimpleToComplexStringVariableFixer (priority -10). `"${name}"` -> `"{$name}"` (a `$` right before the
 * `${` is escaped: `\$`). The tokens change kind, so the file is re-parsed.
 */
final class SimpleToComplexStringVariable extends EditRule
{
    public function name(): string
    {
        return 'simple_to_complex_string_variable';
    }

    public function priority(): int
    {
        return -10;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 3; $i > 0; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES || $s->kind[$i + 1] !== \__McTokId::T_STRING_VARNAME) {
                continue;
            }
            $prev = $s->text($i - 1);
            if (\str_ends_with($prev, '$') && !\str_ends_with($prev, '\$')) {
                $s->edit(Source::tokAt($i - 1), Source::tokAt($i - 1) + 1, \substr($prev, 0, -1) . '\$');
            }
            $s->edit(Source::tokAt($i), Source::tokAt($i) + 1, '{');
            $s->edit(Source::tokAt($i + 1), Source::tokAt($i + 1) + 1, '$' . $s->text($i + 1));
        }
    }
}
