<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\ConstantCaseFixer (priority 0). A plain T_STRING `true` / `false` / `null` (any case) whose
 * neighbours say it is a constant reference gets the configured case. Text rule.
 */
final class ConstantCase extends GapRule
{
    /** @var array<int, bool> */
    private array $forbidPrev = [];

    public function __construct(private bool $upper = false)
    {
        foreach ([\__McTokId::T_DOUBLE_COLON, \__McTokId::T_EXTENDS, \__McTokId::T_IMPLEMENTS, \__McTokId::T_INSTANCEOF, \__McTokId::T_NAMESPACE,
            \__McTokId::T_NEW, \__McTokId::T_NS_SEPARATOR, \__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $k) {
            $this->forbidPrev[$k] = true;
        }
    }

    public function name(): string
    {
        return 'constant_case';
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
        return [\__McTokId::T_STRING];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_STRING || $s->ct[$i] !== 0) {
            return null;
        }
        $w = $s->end[$i] - $s->start[$i];
        if ($w !== 4 && $w !== 5) {
            return null;
        }
        $t = $s->text($i);
        $l = \strtolower($t);
        if ($l !== 'true' && $l !== 'false' && $l !== 'null') {
            return null;
        }
        $new = $this->upper ? \strtoupper($t) : $l;
        if ($new === $t || $i === 0 || $i + 1 >= $s->n) {
            return null;
        }
        $p = $i - 1;
        if ($s->ct[$p] === 0 && isset($this->forbidPrev[$s->kind[$p]])) {
            return null;
        }
        $nk = $s->kind[$i + 1];
        if ($s->ct[$i + 1] === 0 && ($nk === \__McTokId::T_DOUBLE_COLON || $nk === \__McTokId::T_NS_SEPARATOR || $nk === 61)) {
            return null;
        }
        if ($s->kind[$p] === \__McTokId::T_CASE && $s->ct[$p] === 0 && $nk === 59 && $s->ct[$i + 1] === 0) {
            return null;
        }
        return $new;
    }
}
