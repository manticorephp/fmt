<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Semicolon\SemicolonAfterInstructionFixer (priority 2). A `?>` whose previous code token is not `;`, `{`, `}`, `:` or the
 * open tag gets a `;` right behind that token (before the trivia).
 */
final class SemicolonAfterInstruction extends EditRule
{
    public function name(): string
    {
        return 'semicolon_after_instruction';
    }

    public function priority(): int
    {
        return 2;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 2; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_CLOSE_TAG) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = $s->n - 1; $i > 1; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_CLOSE_TAG) {
                continue;
            }
            $p = $i - 1;
            $k = $s->kind[$p];
            if ($k === \__McTokId::T_OPEN_TAG || ($s->ct[$p] === 0 && ($k === 59 || $k === 123 || $k === 125 || $k === 58))) {
                continue;
            }
            $s->edit(Source::gapAt($i), Source::gapAt($i), ';');
        }
    }
}
