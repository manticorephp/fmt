<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\IntegerLiteralCaseFixer (priority 0). `0Xff` -> `0xFF`, `0B1` -> `0b1`, `0O7` -> `0o7`: the
 * prefix lower, the digits upper (T_LNUMBER only). Text rule.
 */
final class IntegerLiteralCase extends TextRule
{
    public function name(): string
    {
        return 'integer_literal_case';
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
        if ($s->kind[$i] !== \__McTokId::T_LNUMBER || $s->end[$i] - $s->start[$i] < 3 || $s->code[$s->start[$i]] !== '0') {
            return null;
        }
        $t = $s->text($i);
        if (\preg_match('#^0([boxBOX])([0-9a-fA-F_]+)$#', $t, $m) !== 1) {
            return null;
        }
        $new = '0' . \strtolower($m[1]) . \strtoupper($m[2]);
        return $new === $t ? null : $new;
    }
}
