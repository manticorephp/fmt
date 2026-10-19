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
    /** @return string[] the gap's trivia tokens as upstream sees them (Gap::pieces), [] for an empty gap */
    public static function pieces(Source $s, int $g): array
    {
        $gap = $s->gapBefore[$g];
        if ($gap === '') {
            return [];
        }
        return ($s->gapFlags[$g] & Gap::G_COMMENT) !== 0 ? Gap::pieces($gap) : [$gap];
    }

    /** IndentationTrait::extractIndent: the blanks after the last line break, '' without one. */
    public static function extractIndent(string $c): string
    {
        $a = \strrpos($c, "\n");
        $b = \strrpos($c, "\r");
        $p = $a === false ? ($b === false ? -1 : $b) : ($b === false || $a > $b ? $a : $b);
        if ($p < 0) {
            return '';
        }
        return \substr($c, $p + 1, \strspn($c, " \t", $p + 1));
    }

    /** Whitespace removed from an open/close tag (`Preg::replace('/\S/', '', $tag)`). */
    public static function tagSpace(string $t): string
    {
        $out = '';
        $n = \strlen($t);
        for ($i = 0; $i < $n; $i++) {
            $c = $t[$i];
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\x0b" || $c === "\x0c") {
                $out .= $c;
            }
        }
        return $out;
    }

    /**
     * WhitespacesAnalyzer::detectIndent for the item right behind the first $k trivia pieces of gap $t: the text after the last
     * "\n" of the nearest whitespace token before it that holds a line break (or that follows an open tag ending in one).
     */
    public static function detectIndent(Source $s, int $t, int $k): string
    {
        $g = $t;
        $limit = $k;
        while (true) {
            $p = self::pieces($s, $g);
            for ($q = \min($limit, \count($p)) - 1; $q >= 0; $q--) {
                if (!Gap::isSpace($p[$q])) {
                    continue;
                }
                $w = $p[$q];
                $nl = \strrpos($w, "\n");
                if ($nl !== false) {
                    return \substr($w, $nl + 1);
                }
                if ($q === 0 && $g > 0 && $s->kind[$g - 1] === \__McTokId::T_OPEN_TAG && $s->ct[$g - 1] === 0) {
                    $tx = $s->text($g - 1);
                    if ($tx !== '' && $tx[\strlen($tx) - 1] === "\n") {
                        return $w;
                    }
                }
            }
            if ($g === 0) {
                return '';
            }
            $g--;
            $limit = \PHP_INT_MAX;
        }
    }

    /**
     * IndentationTrait::getLineIndentation($index): the indent of the nearest whitespace / inline HTML token before token $i
     * that holds a line break (an open/close tag right before it lends its own whitespace), '' when there is none.
     */
    public static function lineIndentation(Source $s, int $i): string
    {
        $op = \__McTokId::T_OPEN_TAG;
        $cl = \__McTokId::T_CLOSE_TAG;
        for ($g = $i; $g > 0; $g--) {
            $f = $s->gapFlags[$g];
            $tag = $s->kind[$g - 1] === $op || $s->kind[$g - 1] === $cl;
            if (($f & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) !== 0 || ($tag && $s->gapBefore[$g] !== '')) {
                $pieces = ($f & Gap::G_COMMENT) !== 0 ? Gap::pieces($s->gapBefore[$g]) : [$s->gapBefore[$g]];
                for ($k = \count($pieces) - 1; $k >= 0; $k--) {
                    $p = $pieces[$k];
                    if (!Gap::isSpace($p)) {
                        continue;
                    }
                    if ($k === 0 && $tag) {
                        $p = self::tagSpace($s->text($g - 1)) . $p;
                    }
                    if (Gap::pieceHasR($p)) {
                        return self::extractIndent($p);
                    }
                }
            }
            if ($s->kind[$g - 1] === \__McTokId::T_INLINE_HTML) {
                $p = $s->text($g - 1);
                if ($g >= 2 && $s->gapBefore[$g - 1] === '' && ($s->kind[$g - 2] === $op || $s->kind[$g - 2] === $cl)) {
                    $p = self::tagSpace($s->text($g - 2)) . $p;
                }
                if (Gap::pieceHasR($p)) {
                    return self::extractIndent($p);
                }
            }
        }
        return '';
    }

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
        foreach (self::switchAnalyses($s) as $a) {
            foreach ($a[3] as $x) {
                $out[$x] = true;
            }
        }
        return $out;
    }

    /**
     * The switch analyses of ControlCaseStructuresAnalyzer::findControlStructures($tokens, [T_SWITCH]), in the order upstream yields them
     * (a switch is complete at its closing brace): [default token index or -1, its `:` / `;` index or -1, case `:` / `;` indexes,
     * every colon of the switch incl. an alternative-syntax switch's own]. [] when upstream throws (several defaults).
     * @return array<int, array{0:int,1:int,2:int[],3:int[]}>
     */
    public static function switchAnalyses(Source $s): array
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
        /** @var int[][] $scase */
        $scase = [];
        /** @var int[] $sdi */
        $sdi = [];
        /** @var int[] $sdc */
        $sdc = [];
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
                $scase[$depth] = [];
                $sdi[$depth] = -1;
                $sdc[$depth] = -1;
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
                        $out[] = [$sdi[$depth], $sdc[$depth], $scase[$depth], $scol[$depth]];
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
                    $out[] = [$sdi[$depth], $sdc[$depth], $scase[$depth], $scol[$depth]];
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
                $q = self::findCaseOpen($s, $i);
                $scol[$depth][] = $q;
                $scase[$depth][] = $q;
            } elseif ($k === \__McTokId::T_DEFAULT && $c === 0) {
                if ($sdef[$depth]) {
                    return []; // upstream throws "multiple default" and leaves the file alone
                }
                $sdef[$depth] = true;
                $sdi[$depth] = $i;
                for ($j = $i + 1; $j < $n; $j++) {
                    if (($s->kind[$j] === 58 && $s->ct[$j] === 0) || $s->kind[$j] === 59) {
                        $scol[$depth][] = $j;
                        $sdc[$depth] = $j;
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

    public const E_PROPERTY = 1;
    public const E_METHOD = 2;
    public const E_PROMOTED = 3;
    public const E_CONST = 4;
    public const E_TRAIT_IMPORT = 5;
    public const E_CASE = 6;

    /**
     * TokensAnalyzer::getClassyElements: element token index => [E_* type, index of the classy token that owns it], ascending.
     * @return array<int, int[]>
     */
    public static function classyElements(Source $s): array
    {
        $out = [];
        $n = $s->n;
        for ($i = 1; $i < $n - 1; $i++) {
            if (self::isClassy($s, $i)) {
                $i = self::findClassyElements($s, $i, $i, $out);
            }
        }
        \ksort($out);
        return $out;
    }

    private static function isClassy(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        return $s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM);
    }

    /** @param array<int, int[]> $out */
    private static function findClassyElements(Source $s, int $classIndex, int $i, array &$out): int
    {
        $curly = 0;
        $braces = 0;
        $n = $s->n;
        for ($i++; $i < $n; $i++) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($k === \__McTokId::T_CLASS && $c === 0) {
                $nested = $i;
                $i++;
                if ($i < $n && $s->kind[$i] === 40 && $s->ct[$i] === 0) {
                    $i++;
                    for ($lv = 1; $i < $n; $i++) {
                        $kk = $s->kind[$i];
                        if ($kk === 40 && $s->ct[$i] === 0) {
                            $lv++;
                            continue;
                        }
                        if ($kk === 41 && $s->ct[$i] === 0) {
                            $lv--;
                            if ($lv === 0) {
                                $i = self::findClassyElements($s, $nested, $i, $out);
                                break;
                            }
                            continue;
                        }
                        if ($kk === \__McTokId::T_CLASS && $s->ct[$i] === 0) {
                            $i = self::findClassyElements($s, $i, $i, $out);
                        }
                    }
                } else {
                    $i = self::findClassyElements($s, $nested, $nested, $out);
                }
                continue;
            }
            if ($c === 0 && $k === 40) {
                $braces++;
                continue;
            }
            if ($c === 0 && $k === 41) {
                $braces--;
                continue;
            }
            if ($c === 0 && $k === 123) {
                $curly++;
                continue;
            }
            if ($c === 0 && $k === 125) {
                $curly--;
                if ($curly === 0) {
                    break;
                }
                continue;
            }
            if ($curly !== 1 || ($c === 0 && $k < 256)) {
                continue; // a single-char token is no array token
            }
            if ($braces === 0 && $k === \__McTokId::T_VARIABLE) {
                $out[$i] = [self::E_PROPERTY, $classIndex];
                continue;
            }
            if ($c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                while ($i + 1 < $n && $s->ct[$i] !== CT::PROPERTY_HOOK_BRACE_CLOSE) {
                    $i++;
                }
                continue;
            }
            if ($k === \__McTokId::T_ATTRIBUTE) {
                $i = $s->pair[$i] >= 0 ? $s->pair[$i] : $i;
                continue;
            }
            if ($k === \__McTokId::T_FUNCTION && $c === 0) {
                $nm = $i + 1; // `&` of `function &f()` is the "name" here, as upstream's getNextMeaningfulToken
                if ($nm >= $n || ($s->kind[$nm] === 40 && $s->ct[$nm] === 0)) {
                    continue;
                }
                $out[$i] = [self::E_METHOD, $classIndex];
                if ($s->text($nm) === '__construct' && $nm + 1 < $n) {
                    $op = $nm + 1;
                    $cp = $s->pair[$op];
                    for ($q = $op; $q >= 0 && $q < $cp; $q++) {
                        $qc = $s->ct[$q];
                        $qk = $s->kind[$q];
                        if ($qc === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $qc === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED
                            || $qc === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE || ($qc === 0 && ($qk === \__McTokId::T_PRIVATE_SET
                            || $qk === \__McTokId::T_PROTECTED_SET || $qk === \__McTokId::T_PUBLIC_SET || $qk === \__McTokId::T_READONLY || $qk === \__McTokId::T_FINAL))) {
                            for ($v = $q + 1; $v < $n; $v++) {
                                if ($s->kind[$v] === \__McTokId::T_VARIABLE) {
                                    $out[$v] = [self::E_PROMOTED, $classIndex];
                                    break;
                                }
                            }
                        }
                    }
                }
            } elseif ($k === \__McTokId::T_CONST && $c === 0) {
                $out[$i] = [self::E_CONST, $classIndex];
            } elseif ($c === CT::USE_TRAIT) {
                $out[$i] = [self::E_TRAIT_IMPORT, $classIndex];
            } elseif ($k === \__McTokId::T_CASE && $c === 0) {
                $out[$i] = [self::E_CASE, $classIndex];
            }
        }
        return $i;
    }
}
