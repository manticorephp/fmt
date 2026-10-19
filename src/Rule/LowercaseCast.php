<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\CastNotation\LowercaseCastFixer and ShortScalarCastFixer (both priority 0, text rules over
 * the cast tokens). $short: `(boolean)` -> `(bool)`, `(integer)` -> `(int)`, `(double)` / `(real)` -> `(float)`, `(binary)` -> `(string)`.
 */
final class LowercaseCast extends GapRule
{
    public function __construct(private bool $short = false)
    {
    }

    public function name(): string
    {
        return $this->short ? 'short_scalar_cast' : 'lowercase_cast';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function usesText(Source $s): bool
    {
        return true;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return $this->short ? [\__McTokId::T_BOOL_CAST, \__McTokId::T_INT_CAST, \__McTokId::T_DOUBLE_CAST, \__McTokId::T_STRING_CAST]
            : [\__McTokId::T_ARRAY_CAST, \__McTokId::T_BOOL_CAST, \__McTokId::T_DOUBLE_CAST, \__McTokId::T_INT_CAST, \__McTokId::T_OBJECT_CAST,
                \__McTokId::T_STRING_CAST, \__McTokId::T_UNSET_CAST, \__McTokId::T_VOID_CAST];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($k < \__McTokId::T_INT_CAST || $k > \__McTokId::T_VOID_CAST) {
            return null; // the Engine offers every rule of a group the token kinds any of them asked for
        }
        $t = $s->text($i);
        if (!$this->short) {
            $l = \strtolower($t);
            return $l === $t ? null : $l;
        }
        $from = \trim(\substr($t, 1, -1));
        $to = match (\strtolower($from)) {
            'boolean' => 'bool',
            'integer' => 'int',
            'double', 'real' => 'float',
            'binary' => 'string',
            default => null,
        };
        return $to === null ? null : \str_replace($from, $to, $t);
    }
}
