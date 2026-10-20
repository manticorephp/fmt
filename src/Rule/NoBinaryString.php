<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\NoBinaryStringFixer (priority 40). The `b` / `B` flag before a string, a heredoc or an
 * interpolated string goes. Zend lexes `b'x'`, `B<<<E` and `b"` as ONE token; the prelude tokenizer splits the flag off as a T_STRING
 * (compiler issue, see docs/design.md), so both shapes are handled: a token that starts with the flag loses its first byte, a lone `b`
 * glued to the string token that follows is deleted.
 */
final class NoBinaryString extends EditRule
{
    public function name(): string
    {
        return 'no_binary_string';
    }

    public function priority(): int
    {
        return 40;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $kinds = $s->kind;
        for ($i = 0; $i < $n; $i++) {
            $k = $kinds[$i];
            if ($k === \__McTokId::T_STRING) {
                if ($s->end[$i] - $s->start[$i] === 1 && $i + 1 < $n && $s->gapBefore[$i + 1] === '' && ($i === 0 || ($kinds[$i - 1] !== \__McTokId::T_OBJECT_OPERATOR && $kinds[$i - 1] !== \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) && ($s->code[$s->start[$i]] === 'b' || $s->code[$s->start[$i]] === 'B')) {
                    $nk = $kinds[$i + 1];
                    if ($nk === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $nk === \__McTokId::T_START_HEREDOC || ($nk === 34 && $s->ct[$i + 1] === 0)) {
                        $s->edit(Source::tokAt($i), Source::tokAt($i) + 1, '');
                    }
                }
            } elseif ($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === \__McTokId::T_START_HEREDOC) {
                $c = $s->code[$s->start[$i]];
                if ($c === 'b' || $c === 'B') {
                    $s->setText($i, \substr($s->text($i), 1));
                }
            }
        }
    }
}
