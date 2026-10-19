<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\LowercaseStaticReferenceFixer (priority 0). `self`, `static`, `parent` in any case but
 * lower, where the neighbours say it is a class reference (isTokenToFix). Text rule.
 */
final class LowercaseStaticReference extends GapRule
{
    public function name(): string
    {
        return 'lowercase_static_reference';
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
        return [\__McTokId::T_STRING, \__McTokId::T_STATIC];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($s->ct[$i] !== 0 || ($k !== \__McTokId::T_STRING && $k !== \__McTokId::T_STATIC)) {
            return null;
        }
        $w = $s->end[$i] - $s->start[$i];
        if ($w !== 4 && $w !== 6) {
            return null;
        }
        $t = $s->text($i);
        $l = \strtolower($t);
        if ($l === $t || ($k === \__McTokId::T_STRING ? ($l !== 'self' && $l !== 'parent') : $l !== 'static') || $i === 0 || $i + 1 >= $s->n) {
            return null;
        }
        $n = $i + 1;
        $nk = $s->kind[$n];
        $nc = $s->ct[$n];
        if ($nk === \__McTokId::T_DOUBLE_COLON && $nc === 0) {
            return $l;
        }
        $nVar = $nc === 0 && $nk === \__McTokId::T_VARIABLE;
        if (!($nVar || $nc === CT::TYPE_ALTERNATION || ($nc === 0 && ($nk === 40 || $nk === 41 || $nk === 123 || $nk === 59)))) {
            return null;
        }
        $p = $i - 1;
        $pk = $s->kind[$p];
        $pc = $s->ct[$p];
        if ($pc === 0 && $pk === \__McTokId::T_INSTANCEOF) {
            return $l;
        }
        if ($pc === 0 && $pk === \__McTokId::T_CASE) {
            return $nc === 0 && $nk === 59 ? null : $l;
        }
        $pOpen = $pc === 0 && ($pk === 40 || $pk === 123);
        if (!($pOpen || $pc === CT::NULLABLE_TYPE || $pc === CT::TYPE_COLON || $pc === CT::TYPE_ALTERNATION
            || ($pc === 0 && ($pk === \__McTokId::T_NEW || $pk === \__McTokId::T_PRIVATE || $pk === \__McTokId::T_PROTECTED || $pk === \__McTokId::T_PUBLIC)))) {
            return null;
        }
        if ($pc === 0 && $pk === 40 && $nc === 0 && $nk === 41) {
            return null;
        }
        if ($l === 'static' && $nVar) {
            return null;
        }
        return $l;
    }
}
