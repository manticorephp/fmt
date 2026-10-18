<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Ports of the upstream analyzers the rules share (TokensAnalyzer, SwitchAnalyzer, ...), over the immutable token columns.
 * "Next/previous meaningful token" of upstream is simply i + 1 / i - 1 here: trivia lives in the gaps. An upstream
 * `isGivenKind(T_X)` is `kind === T_X && ct === 0` (a custom kind replaces the id), `equals('{')` is `kind === 123 && ct === 0`.
 */
final class Analyzer
{
    /** TokensAnalyzer::isLambda for a T_FUNCTION / T_FN at $i. */
    public static function isLambda(Source $s, int $i): bool
    {
        $j = $i + 1;
        if ($j < $s->n && $s->ct[$j] === CT::RETURN_REF) {
            $j++;
        }
        return $j < $s->n && $s->kind[$j] === 40 && $s->ct[$j] === 0;
    }

    /** TokensAnalyzer::isAnonymousClass for a classy token at $i. */
    public static function isAnonymousClass(Source $s, int $i): bool
    {
        if ($s->kind[$i] !== \__McTokId::T_CLASS || $s->ct[$i] !== 0) {
            return false;
        }
        $j = $i - 1;
        if ($j >= 0 && $s->kind[$j] === \__McTokId::T_READONLY) {
            $j--;
        }
        while ($j >= 0 && $s->ct[$j] === CT::ATTRIBUTE_CLOSE) {
            $j = $s->pair[$j] - 1;
        }
        return $j >= 0 && $s->kind[$j] === \__McTokId::T_NEW;
    }

    /**
     * SwitchAnalyzer::belongsToSwitch as a set: the `:` (or `;`) that opens every case / default of every switch plus the
     * `:` of an alternative-syntax switch (ControlCaseStructuresAnalyzer::findControlStructures($tokens, [T_SWITCH])).
     * @return array<int, bool>
     */
    public static function switchColons(Source $s): array
    {
        $out = [];
        $n = $s->n;
        $found = false;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_SWITCH && $s->ct[$i] === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return $out;
        }
        $depth = -1;
        /** @var int[] $skind */
        $skind = [];
        /** @var int[] $sbrace */
        $sbrace = [];
        /** @var bool[] $salt */
        $salt = [];
        /** @var bool[] $sdef */
        $sdef = [];
        /** @var int[][] $scol */
        $scol = [];
        $interest = false;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($c === 0 && ($k === \__McTokId::T_SWITCH || $k === \__McTokId::T_MATCH || $k === \__McTokId::T_ENUM)) {
                $depth++;
                $skind[$depth] = $k;
                $sbrace[$depth] = 0;
                $salt[$depth] = false;
                $sdef[$depth] = false;
                $scol[$depth] = [];
                $interest = $k === \__McTokId::T_SWITCH;
                if ($interest) {
                    $open = $s->pair[$i + 1] + 1;
                    if ($open < $n && $s->kind[$open] === 58 && $s->ct[$open] === 0) {
                        $salt[$depth] = true;
                        $scol[$depth][] = $open;
                    }
                }
                continue;
            }
            if ($depth < 0) {
                continue;
            }
            if ($k === 123 && $c === 0) {
                $sbrace[$depth]++;
                continue;
            }
            if ($k === 125 && $c === 0) {
                $sbrace[$depth]--;
                if ($sbrace[$depth] === 0) {
                    if ($salt[$depth]) {
                        continue;
                    }
                    if ($interest) {
                        foreach ($scol[$depth] as $x) {
                            $out[$x] = true;
                        }
                    }
                    $depth--;
                    if ($depth >= 0) {
                        $interest = $skind[$depth] === \__McTokId::T_SWITCH;
                    }
                }
                continue;
            }
            if ($k === \__McTokId::T_ENDSWITCH) {
                if ($interest) {
                    foreach ($scol[$depth] as $x) {
                        $out[$x] = true;
                    }
                }
                $depth--;
                if ($depth >= 0) {
                    $interest = $skind[$depth] === \__McTokId::T_SWITCH;
                }
                if ($depth < 0) {
                    continue;
                }
            }
            if (!$interest) {
                continue;
            }
            if ($k === \__McTokId::T_CASE && $c === 0) {
                $scol[$depth][] = self::findCaseOpen($s, $i);
            } elseif ($k === \__McTokId::T_DEFAULT && $c === 0) {
                if ($sdef[$depth]) {
                    return []; // upstream throws "multiple default" and leaves the file alone
                }
                $sdef[$depth] = true;
                for ($j = $i + 1; $j < $n; $j++) {
                    if (($s->kind[$j] === 58 && $s->ct[$j] === 0) || $s->kind[$j] === 59) {
                        $scol[$depth][] = $j;
                        break;
                    }
                }
            }
        }
        return $out;
    }

    /** ControlCaseStructuresAnalyzer::findCaseOpen for a switch `case` at $i: the `:` / `;` ending its label. */
    public static function findCaseOpen(Source $s, int $i): int
    {
        $tern = 0;
        $n = $s->n;
        for ($j = $i + 1; $j < $n; $j++) {
            $k = $s->kind[$j];
            if (($k === 40 || $k === 123) && $s->ct[$j] === 0) {
                $j = $s->pair[$j];
                continue;
            }
            if ($k === 63) {
                $tern++;
                continue;
            }
            if (($k === 58 && $s->ct[$j] === 0) || $k === 59) {
                if ($tern === 0) {
                    return $j;
                }
                $tern--;
            }
        }
        return $n - 1;
    }
}
