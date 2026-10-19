<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\TernaryOperatorSpacesFixer (priority 1). Every ternary `?` / `:` (kind 63 / 58
 * without a custom kind; a `:` of a switch case, alternative syntax or goto label is not one) gets a space on each side
 * unless that side is already a line break; `?:` and `? :` lose the gap between them. Each operator only touches the gap
 * before and the gap after itself, so the rule is a gap function triggered by the two kinds.
 */
final class TernaryOperatorSpaces extends GapRule
{
    private const QUESTION = 63;
    private const COLON = 58;

    public function name(): string
    {
        return 'ternary_operator_spaces';
    }

    public function priority(): int
    {
        return 1;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [self::QUESTION, self::COLON];
    }

    public function beforeKinds(): array
    {
        return [self::QUESTION, self::COLON];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        // upstream walks the operators backwards: the operator after the gap (its space before) goes first
        if ($i < $s->n && self::isOp($s, $i)) {
            $gap = $this->before($gap, $s, $i);
        }
        if ($i > 0 && self::isOp($s, $i - 1)) {
            $gap = $this->after($gap, $s, $i);
        }
        return $gap;
    }

    private function before(string $gap, Source $s, int $i): string
    {
        if ($s->kind[$i] === self::COLON && $i > 0 && $s->kind[$i - 1] === self::QUESTION && $s->ct[$i - 1] === 0 && !Gap::hasComment($gap)) {
            return $gap; // `? :` is handled by the `?`
        }
        if ($gap === '') {
            return ' ';
        }
        if (!Gap::hasComment($gap)) {
            return \strpos($gap, "\n") === false ? ' ' : $gap;
        }
        $pieces = Gap::pieces($gap);
        return Gap::isSpace($pieces[\count($pieces) - 1]) ? $gap : $gap . ' ';
    }

    /** $i = the gap after the operator at $i - 1 */
    private function after(string $gap, Source $s, int $i): string
    {
        if ($s->kind[$i - 1] === self::QUESTION && $i < $s->n && $s->kind[$i] === self::COLON && $s->ct[$i] === 0 && !Gap::hasComment($gap)) {
            return ''; // `?:` / `? :`
        }
        if ($gap === '') {
            return ' ';
        }
        $c = \ord($gap[0]);
        if ($c === 35 || $c === 47) {
            return ' ' . $gap;
        }
        $ws = \strcspn($gap, '#/');
        if (\strpos($gap, "\n") !== false && \strpos($gap, "\n") < $ws) {
            return $gap;
        }
        return ' ' . \substr($gap, $ws);
    }

    public static function isOp(Source $s, int $j): bool
    {
        $k = $s->kind[$j];
        if ($s->ct[$j] !== 0) {
            return false;
        }
        if ($k === self::QUESTION) {
            return true;
        }
        if ($k !== self::COLON) {
            return false;
        }
        if (isset($s->switchColons()[$j])) {
            return false;
        }
        $p = $s->kind[$j - 1];
        if ($p === \__McTokId::T_ELSE) {
            return false;
        }
        if ($p === 41 && $s->ct[$j - 1] === 0) {
            $o = $s->pair[$j - 1];
            if ($o > 0) {
                $b = $s->kind[$o - 1];
                if ($b === \__McTokId::T_DECLARE || $b === \__McTokId::T_ELSEIF || $b === \__McTokId::T_FOR || $b === \__McTokId::T_FOREACH
                    || $b === \__McTokId::T_IF || $b === \__McTokId::T_SWITCH || $b === \__McTokId::T_WHILE) {
                    return false;
                }
            }
        }
        if ($p === \__McTokId::T_STRING && $s->ct[$j - 1] === 0 && $j >= 2) {
            $q = $s->kind[$j - 2];
            $qc = $s->ct[$j - 2];
            if (($qc === 0 && ($q === 58 || $q === 59 || $q === 123 || $q === 125)) || $q === \__McTokId::T_OPEN_TAG) {
                return false;
            }
        }
        return true;
    }
}
