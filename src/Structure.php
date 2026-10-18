<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Built once per Source from the ORIGINAL token stream; gap rules read it and never re-scan.
 * Token kinds never change, so pair, depth, stmtStart/stmtEnd and blockKind stay valid for the whole gap phase.
 * lineStart is a snapshot of the original gaps and goes stale once a rule rewrites a gap.
 *
 * Statements are SEGMENTS: a segment ends at `;` (outside parens/brackets), at `{` (the header) and
 * at `}` (a one-token segment). `->{expr}` splits like a block; extend here when a rule needs better.
 */
final class Structure
{
    public const BK_NONE = 0;
    public const BK_CLASS = 1;
    public const BK_FUNCTION = 2;
    public const BK_CONTROL = 3;
    public const BK_MATCH = 4;
    public const BK_NAMESPACE = 5;
    public const BK_OTHER = 6;

    /** @var int[] index of the matching bracket, -1 when none (or not a bracket) */
    public array $pair = [];
    /** @var int[] number of enclosing brackets; an open/close pair shares one value */
    public array $depth = [];
    /** @var int[] */
    public array $stmtStart = [];
    /** @var int[] */
    public array $stmtEnd = [];
    /** @var int[] 1 when the token is first on its line */
    public array $lineStart = [];
    /** @var int[] BK_* for `{` tokens, BK_NONE elsewhere */
    public array $blockKind = [];

    private static function isOpen(int $k): bool
    {
        return $k === 40 || $k === 91 || $k === 123 || $k === \__McTokId::T_CURLY_OPEN
            || $k === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES || $k === \__McTokId::T_ATTRIBUTE;
    }

    /** 41 for `(`, 93 for `[` and `#[`, 125 for every brace flavour */
    private static function closerOf(int $k): int
    {
        if ($k === 40) {
            return 41;
        }
        if ($k === 91 || $k === \__McTokId::T_ATTRIBUTE) {
            return 93;
        }
        return 125;
    }

    private static function blockKindOf(Source $s, Structure $st, int $brace): int
    {
        $i = $brace - 1;
        while ($i >= 0) {
            $k = $s->kind[$i];
            if ($k === 41 || $k === 93) {
                $p = $st->pair[$i];
                $i = $p >= 0 ? $p - 1 : $i - 1;
                continue;
            }
            if ($k === 59 || $k === 123 || $k === 125) {
                return self::BK_OTHER;
            }
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                return self::BK_CLASS;
            }
            if ($k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_FN) {
                return self::BK_FUNCTION;
            }
            if ($k === \__McTokId::T_MATCH) {
                return self::BK_MATCH;
            }
            if ($k === \__McTokId::T_NAMESPACE) {
                return self::BK_NAMESPACE;
            }
            if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_WHILE
                || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_TRY
                || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_FINALLY || $k === \__McTokId::T_DO || $k === \__McTokId::T_DECLARE) {
                return self::BK_CONTROL;
            }
            $i--;
        }
        return self::BK_OTHER;
    }

    public static function build(Source $s): Structure
    {
        $st = new Structure();
        $n = $s->n;
        $pair = [];
        $depth = [];
        $stmtStart = [];
        $stmtEnd = [];
        $lineStart = [];
        $blockKind = [];
        for ($i = 0; $i < $n; $i++) {
            $pair[] = -1;
            $depth[] = 0;
            $stmtStart[] = 0;
            $stmtEnd[] = 0;
            $lineStart[] = 0;
            $blockKind[] = self::BK_NONE;
        }
        $st->pair = $pair;

        // pass 1: pairing + depth
        /** @var int[] $stack */
        $stack = [];
        $sp = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (self::isOpen($k)) {
                $depth[$i] = $sp;
                $stack[$sp] = $i;
                $sp++;
            } elseif (($k === 41 || $k === 93 || $k === 125) && $sp > 0 && self::closerOf($s->kind[$stack[$sp - 1]]) === $k) {
                $sp--;
                $o = $stack[$sp];
                $pair[$o] = $i;
                $pair[$i] = $o;
                $depth[$i] = $sp;
            } else {
                $depth[$i] = $sp;
            }
        }
        $st->pair = $pair;
        $st->depth = $depth;

        // pass 2: statement segments, line starts, block kinds
        /** @var int[] $pcStack paren count saved at each open block `{` */
        $pcStack = [];
        $fp = 0;
        $segStart = 0;
        $pc = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($i === 0) {
                $lineStart[$i] = 1;
            } elseif ($s->gapBefore[$i] !== '') {
                if (\strpbrk($s->gapBefore[$i], "\r\n") !== false) {
                    $lineStart[$i] = 1;
                }
            } else {
                $lb = \ord($s->code[$s->end[$i - 1] - 1]);
                if ($lb === 10 || $lb === 13) {
                    $lineStart[$i] = 1;
                }
            }
            if ($k === 40 || $k === 91 || $k === \__McTokId::T_ATTRIBUTE) {
                $pc++;
            } elseif (($k === 41 || $k === 93) && $pc > 0) {
                $pc--;
            } elseif ($k === 123) {
                $blockKind[$i] = self::blockKindOf($s, $st, $i);
                for ($j = $segStart; $j <= $i; $j++) {
                    $stmtStart[$j] = $segStart;
                    $stmtEnd[$j] = $i;
                }
                $segStart = $i + 1;
                $pcStack[$fp] = $pc;
                $fp++;
                $pc = 0;
            } elseif ($k === 59 && $pc === 0) {
                for ($j = $segStart; $j <= $i; $j++) {
                    $stmtStart[$j] = $segStart;
                    $stmtEnd[$j] = $i;
                }
                $segStart = $i + 1;
            } elseif ($k === 125 && ($pair[$i] < 0 || $s->kind[$pair[$i]] === 123)) {
                for ($j = $segStart; $j < $i; $j++) {
                    $stmtStart[$j] = $segStart;
                    $stmtEnd[$j] = $i - 1;
                }
                $stmtStart[$i] = $i;
                $stmtEnd[$i] = $i;
                if ($fp > 0) {
                    $fp--;
                    $pc = $pcStack[$fp];
                }
                $segStart = $i + 1;
            }
        }
        for ($j = $segStart; $j < $n; $j++) {
            $stmtStart[$j] = $segStart;
            $stmtEnd[$j] = $n - 1;
        }
        $st->stmtStart = $stmtStart;
        $st->stmtEnd = $stmtEnd;
        $st->lineStart = $lineStart;
        $st->blockKind = $blockKind;
        return $st;
    }
}
