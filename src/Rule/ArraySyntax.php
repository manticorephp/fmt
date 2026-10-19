<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer (priority 37), syntax=short. `array(..)` becomes `[..]`: the parens turn
 * into brackets, `array` goes and the trivia on both sides of it is joined (clearTokenAndMergeSurroundingWhitespace leaves the same bytes).
 */
final class ArraySyntax extends EditRule
{
    public function name(): string
    {
        return 'array_syntax';
    }

    public function priority(): int
    {
        return 37;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_ARRAY || $s->ct[$i] !== 0) {
                continue;
            }
            $o = $i + 1;
            while ($o < $n && !($s->kind[$o] === 40 && $s->ct[$o] === 0)) {
                $o++;
            }
            if ($o >= $n || $s->pair[$o] < 0) {
                continue;
            }
            $s->edit(Source::tokAt($i), Source::tokAt($i) + 1, '');
            $s->edit(Source::tokAt($o), Source::tokAt($o) + 1, '[');
            $s->edit(Source::tokAt($s->pair[$o]), Source::tokAt($s->pair[$o]) + 1, ']');
        }
    }
}
