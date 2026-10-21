<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\StringLineEndingFixer (priority 0, risky). Every `\R` of a string literal / interpolated text / inline HTML token
 * becomes the configured line ending. Text rule.
 */
final class StringLineEnding extends TextRule
{
    public function __construct(private string $lineEnding)
    {
    }

    public function name(): string
    {
        return 'string_line_ending';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_CONSTANT_ENCAPSED_STRING, \__McTokId::T_ENCAPSED_AND_WHITESPACE, \__McTokId::T_INLINE_HTML];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($k !== \__McTokId::T_CONSTANT_ENCAPSED_STRING && $k !== \__McTokId::T_ENCAPSED_AND_WHITESPACE && $k !== \__McTokId::T_INLINE_HTML) {
            return null;
        }
        $t = $s->text($i);
        if (\strpbrk($t, "\r\n\x0b\x0c\x85\xC2\xE2") === false) {
            return null;
        }
        $r = Trivia::preplace('#\R#', $this->lineEnding, $t);
        return $r === $t ? null : $r;
    }
}
