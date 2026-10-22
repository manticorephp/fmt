<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\OctalNotationFixer (priority 0). A T_LNUMBER `0_*[0-7_]+` gets the `0o` prefix (`0123` -> `0o123`). Text rule.
 */
final class OctalNotation extends TextRule
{
    public function name(): string
    {
        return 'octal_notation';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_LNUMBER];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_LNUMBER || $s->end[$i] - $s->start[$i] < 2 || $s->code[$s->start[$i]] !== '0') {
            return null;
        }
        $t = $s->text($i);
        if (\preg_match('#^0_*+([0-7_]+)$#', $t, $m) !== 1) {
            return null;
        }
        return '0o' . $m[1];
    }
}
