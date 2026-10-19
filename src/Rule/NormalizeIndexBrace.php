<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Source;
use Fmt\TextRule;

/** Parity target: PhpCsFixer\Fixer\ArrayNotation\NormalizeIndexBraceFixer (priority 0). `$a{0}` -> `$a[0]` (a parse error since PHP 8, kept for parity). Text rule. */
final class NormalizeIndexBrace extends TextRule
{
    public function name(): string
    {
        return 'normalize_index_brace';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [123, 125];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $c = $s->ct[$i];
        if ($c === CT::ARRAY_INDEX_BRACE_OPEN) {
            return '[';
        }
        return $c === CT::ARRAY_INDEX_BRACE_CLOSE ? ']' : null;
    }
}
