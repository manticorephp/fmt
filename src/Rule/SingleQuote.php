<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer (priority 10). `"abc"` -> `'abc'` when the content has no escape but `\"`, `\$`, `\\`
 * (an odd run of backslashes before anything else blocks it) and, unless strings_containing_single_quote_chars, no `'`; `\"` and `\$` lose their
 * backslash, `'` gains one. A `b` / `B` prefix stays. Text rule.
 */
final class SingleQuote extends TextRule
{
    public function __construct(private bool $withSingleQuotes)
    {
    }

    public function name(): string
    {
        return 'single_quote';
    }

    public function priority(): int
    {
        return 10;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_CONSTANT_ENCAPSED_STRING];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }
        $c = $s->code[$s->start[$i]];
        if ($c !== '"' && $c !== 'b' && $c !== 'B') {
            return null;
        }
        $content = $s->text($i);
        $prefix = '';
        if (\strtolower($content[0]) === 'b') {
            $prefix = $content[0];
            $content = \substr($content, 1);
        }
        if ($content[0] !== '"' || (!$this->withSingleQuotes && \str_contains($content, "'")) || Trivia::pmatch('/(?<!\\\)(?:\\\{2})*\\\(?!["$\\\])/', $content)) {
            return null;
        }
        $content = \substr($content, 1, -1);
        $content = \str_replace(['\"', '\$', '\''], ['"', '$', '\\\''], $content);
        return $prefix . '\'' . $content . '\'';
    }
}
