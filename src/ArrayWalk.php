<?php

declare(strict_types=1);

namespace Fmt;

/** Shared by the array-comma rules (no_whitespace_before_comma_in_array, whitespace_after_comma_in_array): upstream's array scan. */
final class ArrayWalk
{
    /**
     * Every `array(` / `[` array literal (T_ARRAY, ARRAY_BRACKET_OPEN) as [open paren / bracket, its closer], in descending order of the
     * opening token's position (upstream walks the token list from the end).
     * @return int[][]
     */
    public static function arrays(Source $s): array
    {
        $out = [];
        for ($a = $s->n - 1; $a >= 0; $a--) {
            if ($s->ct[$a] === CT::ARRAY_BRACKET_OPEN) {
                $out[] = [$a, $s->pair[$a]];
            } elseif ($s->kind[$a] === \__McTokId::T_ARRAY && $s->ct[$a] === 0 && $a + 1 < $s->n && $s->kind[$a + 1] === 40 && $s->pair[$a + 1] > 0) {
                $out[] = [$a + 1, $s->pair[$a + 1]];
            }
        }
        return $out;
    }

    /**
     * skipNonArrayElements for the token at $j (walking down): a `}` jumps to its `{`, a call's / group's `)` to the token before its
     * `(`. A nested array literal is jumped over as well (its commas are visited from its own range; every rule decides per comma, so the
     * outcome is the same). Returns the new index; a comma of an `implements` list is reported by returning -1.
     */
    public static function skip(Source $s, int $j): int
    {
        $k = $s->kind[$j];
        if ($s->ct[$j] === CT::ARRAY_BRACKET_CLOSE) {
            return $s->pair[$j];
        }
        if ($s->ct[$j] !== 0) {
            return $j;
        }
        if ($k === 125) {
            return $s->pair[$j];
        }
        if ($k === 41) {
            return $s->pair[$j] - 1;
        }
        if ($k === 44 && self::implementsComma($s, $j)) {
            return -1;
        }
        return $j;
    }

    private static function implementsComma(Source $s, int $j): bool
    {
        $i = $j;
        do {
            $i--;
            if ($i < 0) {
                return false;
            }
            $k = $s->kind[$i];
            $c = $s->ct[$i];
        } while ($c === 0 && ($k === \__McTokId::T_STRING || $k === 44));
        return $c === 0 && $k === \__McTokId::T_IMPLEMENTS;
    }
}
