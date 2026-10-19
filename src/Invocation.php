<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Doc\NsScan;

/**
 * Ports of upstream's name-usage analyzers over a Source (code tokens only: upstream's prev / next meaningful token is i - 1 / i + 1):
 * TokensAnalyzer::isConstantInvocation, ClassyAnalyzer::isClassyInvocation, FunctionsAnalyzer::isGlobalFunctionUsage,
 * AttributeAnalyzer::isAttribute, GotoLabelAnalyzer::belongsToGoToLabel. A token's id is its custom kind when it has one (eff).
 */
final class Invocation
{
    public static function eff(Source $s, int $i): int
    {
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    private static function isClassy(int $k): bool
    {
        return $k === \__McTokId::T_CLASS || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_ENUM;
    }

    /** GotoLabelAnalyzer::belongsToGoToLabel for the `:` at $i */
    public static function gotoLabel(Source $s, int $i): bool
    {
        if ($i < 2 || self::eff($s, $i) !== 58 || self::eff($s, $i - 1) !== \__McTokId::T_STRING) {
            return false;
        }
        $p = self::eff($s, $i - 2);
        return $p === 58 || $p === 59 || $p === 123 || $p === 125 || $p === \__McTokId::T_OPEN_TAG;
    }

    /** AttributeAnalyzer::isAttribute (the caller checks that the file has an attribute at all) */
    public static function isAttribute(Source $s, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $k = self::eff($s, $j);
            if ($k === 59 || $k === 123 || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_OPEN_TAG
                || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC
                || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_VARIABLE || $k === CT::ATTRIBUTE_CLOSE) {
                break;
            }
        }
        if ($j < 0 || $s->kind[$j] !== \__McTokId::T_ATTRIBUTE) {
            return false;
        }
        $count = 0;
        for ($q = $j + 1; $q < $i; $q++) {
            $k = self::eff($s, $q);
            if ($k === 40) {
                $count++;
            } elseif ($k === 41) {
                $count--;
            }
        }
        return $count === 0;
    }

    /** TokensAnalyzer::isConstantInvocation for the T_STRING at $i */
    public static function isConstantInvocation(Source $s, int $i, bool $hasEnum, bool $hasAttr): bool
    {
        $n = $s->n;
        $nx = $i + 1;
        $nk = $nx < $n ? self::eff($s, $nx) : -1;
        if ($nk === 40 || $nk === 123 || $nk === \__McTokId::T_DOUBLE_COLON || $nk === \__McTokId::T_ELLIPSIS || $nk === \__McTokId::T_NS_SEPARATOR
            || $nk === CT::RETURN_REF || $nk === CT::TYPE_ALTERNATION || $nk === CT::TYPE_INTERSECTION || $nk === \__McTokId::T_VARIABLE) {
            return false;
        }
        $pv = $i - 1;
        if ($nk === \__McTokId::T_AS && self::eff($s, $pv) !== 40) {
            return false;
        }
        $pk = self::eff($s, $pv);
        if (self::isClassy($pk)) {
            return false;
        }
        if ($pk === \__McTokId::T_AS || $pk === \__McTokId::T_CONST || $pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_FUNCTION
            || $pk === \__McTokId::T_GOTO || $pk === CT::GROUP_IMPORT_BRACE_OPEN || $pk === CT::TYPE_COLON || $pk === CT::TYPE_ALTERNATION
            || $pk === CT::TYPE_INTERSECTION || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
            return false;
        }
        if ($pk === \__McTokId::T_CASE && $hasEnum) {
            for ($j = $i - 1; $j >= 0; $j--) {
                $kj = self::eff($s, $j);
                if ($kj === \__McTokId::T_SWITCH || $kj === \__McTokId::T_ENUM) {
                    break;
                }
            }
            if ($j < 0 || self::eff($s, $j) !== \__McTokId::T_SWITCH) {
                return false;
            }
        }
        while ($pv >= 0 && (($pk = self::eff($s, $pv)) === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::ARRAY_TYPEHINT)) {
            $pv--;
        }
        $pk = $pv >= 0 ? self::eff($s, $pv) : -1;
        if ($pk === CT::CONST_IMPORT || $pk === \__McTokId::T_EXTENDS || $pk === CT::FUNCTION_IMPORT || $pk === \__McTokId::T_IMPLEMENTS
            || $pk === \__McTokId::T_INSTANCEOF || $pk === \__McTokId::T_INSTEADOF || $pk === \__McTokId::T_NAMESPACE || $pk === \__McTokId::T_NEW
            || $pk === CT::NULLABLE_TYPE || $pk === CT::TYPE_COLON || $pk === \__McTokId::T_USE || $pk === CT::USE_TRAIT || $pk === CT::TYPE_INTERSECTION
            || $pk === CT::TYPE_ALTERNATION || $pk === \__McTokId::T_CONST || $pk === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE) {
            return false;
        }
        if (($nk === 38 || $nk === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $nk === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)
            && $nx + 1 < $n && self::eff($s, $nx + 1) === \__McTokId::T_VARIABLE) {
            for ($j = $pv - 1; $j >= 0; $j--) {
                $kj = self::eff($s, $j);
                if ($kj === 59 || $kj === 123 || $kj === 125 || $kj === \__McTokId::T_FUNCTION || $kj === \__McTokId::T_OPEN_TAG || $kj === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                    break;
                }
            }
            if ($j >= 0 && self::eff($s, $j) === \__McTokId::T_FUNCTION) {
                return false;
            }
        }
        if ($pk === 44) {
            $c = $pv;
            while ($c >= 0 && (($ck = self::eff($s, $c)) === 44 || $ck === \__McTokId::T_AS || $ck === CT::NAMESPACE_OPERATOR || $ck === \__McTokId::T_NS_SEPARATOR || $ck === \__McTokId::T_STRING)) {
                $c--;
            }
            $ck = $c >= 0 ? self::eff($s, $c) : -1;
            if ($ck === \__McTokId::T_EXTENDS || $ck === CT::GROUP_IMPORT_BRACE_OPEN || $ck === \__McTokId::T_IMPLEMENTS || $ck === \__McTokId::T_USE || $ck === CT::USE_TRAIT) {
                return false;
            }
        }
        if ($pk === 91 && $nk === 93 && $nx + 1 < $n) {
            $ck = self::eff($s, $nx + 1);
            if ($ck === 34 || $ck === \__McTokId::T_CURLY_OPEN || $ck === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES || $ck === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $ck === \__McTokId::T_VARIABLE) {
                return false;
            }
        }
        if ($hasAttr && self::isAttribute($s, $i)) {
            return false;
        }
        if ($nk === 58 && self::gotoLabel($s, $nx)) {
            return false;
        }
        while ($pv >= 0 && (($pk = self::eff($s, $pv)) === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::TYPE_ALTERNATION)) {
            $pv--;
        }
        if ($pv >= 1 && self::eff($s, $pv) === 40 && self::eff($s, $pv - 1) === \__McTokId::T_CATCH) {
            return false;
        }
        return true;
    }

    /** ClassyAnalyzer::isClassyInvocation for the T_STRING at $i */
    public static function isClassyInvocation(Source $s, int $i, bool $hasAttr): bool
    {
        $n = $s->n;
        if (\in_array(\strtolower($s->text($i)), ['array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'list', 'mixed', 'never', 'null',
            'object', 'parent', 'resource', 'self', 'static', 'string', 'true', 'void'], true)) {
            return false;
        }
        $next = $i + 1;
        $nk = $next < $n ? self::eff($s, $next) : -1;
        if ($nk === \__McTokId::T_NS_SEPARATOR) {
            return false;
        }
        if ($nk === \__McTokId::T_DOUBLE_COLON || $nk === \__McTokId::T_ELLIPSIS || $nk === CT::TYPE_ALTERNATION || $nk === CT::TYPE_INTERSECTION || $nk === \__McTokId::T_VARIABLE) {
            return true;
        }
        $prev = $i - 1;
        while ($prev >= 0 && (($pk = self::eff($s, $prev)) === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING)) {
            $prev--;
        }
        $pk = $prev >= 0 ? self::eff($s, $prev) : -1;
        if ($pk === \__McTokId::T_EXTENDS || $pk === \__McTokId::T_INSTANCEOF || $pk === \__McTokId::T_INSTEADOF || $pk === \__McTokId::T_IMPLEMENTS
            || $pk === \__McTokId::T_NEW || $pk === CT::NULLABLE_TYPE || $pk === CT::TYPE_ALTERNATION || $pk === CT::TYPE_INTERSECTION || $pk === CT::TYPE_COLON
            || $pk === CT::USE_TRAIT) {
            return true;
        }
        if ($nk === 41 && $pk === 40 && $prev >= 1 && self::eff($s, $prev - 1) === \__McTokId::T_CATCH) {
            return true;
        }
        if ($hasAttr && self::isAttribute($s, $i)) {
            return true;
        }
        if (($nk === 38 || $nk === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $nk === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)
            && $next + 1 < $n && self::eff($s, $next + 1) === \__McTokId::T_VARIABLE) {
            for ($j = $prev; $j >= 0; $j--) {
                $kj = self::eff($s, $j);
                if ($kj === 59 || $kj === 123 || $kj === 125 || $kj === \__McTokId::T_FUNCTION || $kj === \__McTokId::T_OPEN_TAG || $kj === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                    break;
                }
            }
            return $j >= 0 && self::eff($s, $j) === \__McTokId::T_FUNCTION;
        }
        if ($pk !== 44) {
            return false;
        }
        do {
            $prev--;
        } while ($prev >= 0 && (($pk = self::eff($s, $prev)) === 44 || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::NAMESPACE_OPERATOR));
        $pk = $prev >= 0 ? self::eff($s, $prev) : -1;
        return $pk === \__McTokId::T_IMPLEMENTS || $pk === CT::USE_TRAIT;
    }

    /**
     * FunctionsAnalyzer::isGlobalFunctionUsage for the T_STRING at $i. $decl = the function-name token indexes of the file
     * (functionDeclarations), $imports = [use token, `;` token, short name, full name] of each function import.
     * @param int[] $decl
     * @param array<int, array{0: int, 1: int, 2: string, 3: string}> $imports
     */
    public static function isGlobalFunctionUsage(Source $s, int $i, NsScan $ns, array $decl, array $imports, bool $hasAttr): bool
    {
        $n = $s->n;
        if (self::eff($s, $i) !== \__McTokId::T_STRING || $i + 1 >= $n || self::eff($s, $i + 1) !== 40) {
            return false;
        }
        $open = $i + 1;
        $prev = $i - 1;
        $nsSep = false;
        if ($prev >= 0 && self::eff($s, $prev) === \__McTokId::T_NS_SEPARATOR) {
            $nsSep = true;
            $prev--;
        }
        $pk = $prev >= 0 ? self::eff($s, $prev) : -1;
        if ($pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_FUNCTION || $pk === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NEW
            || $pk === CT::RETURN_REF || $pk === \__McTokId::T_STRING || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR
            || $pk === \__McTokId::T_ATTRIBUTE) {
            return false;
        }
        if ($nsSep) {
            return true;
        }
        $name = \strtolower($s->text($i));
        if ($name === 'set') {
            if (!($pk === CT::PROPERTY_HOOK_BRACE_OPEN || $pk === 59 || $pk === 125)) {
                return true;
            }
            $close = $s->pair[$open];
            if ($close >= 0 && $close + 1 < $n) {
                $ak = self::eff($s, $close + 1);
                if ($ak === 123 || $ak === \__McTokId::T_DOUBLE_ARROW) {
                    return false;
                }
            }
        }
        [$start, $end, $global] = $ns->tokenScope($i);
        if (!$global) {
            foreach ($decl as $d) {
                if ($d >= $start && $d <= $end && \strtolower($s->text($d)) === $name) {
                    return false;
                }
            }
        }
        foreach ($imports as $u) {
            if ($u[0] < $start || $u[1] > $end || $name !== \strtolower($u[2])) {
                continue;
            }
            return $u[2] === \ltrim($u[3], '\\');
        }
        if ($hasAttr && self::isAttribute($s, $i)) {
            return false;
        }
        return true;
    }

    /**
     * FunctionsAnalyzer::buildFunctionsAnalysis declarations: the name tokens of the functions declared outside classy bodies.
     * @return int[]
     */
    public static function functionDeclarations(Source $s): array
    {
        $out = [];
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            $k = self::eff($s, $i);
            if (self::isClassy($k)) {
                for ($i++; $i < $n && !((self::eff($s, $i) === 40) || self::eff($s, $i) === 123); $i++) {
                }
                if ($i < $n && self::eff($s, $i) === 40) {
                    $i = $s->pair[$i];
                    for ($i++; $i < $n && self::eff($s, $i) !== 123; $i++) {
                    }
                }
                if ($i >= $n || $s->pair[$i] < 0) {
                    break;
                }
                $i = $s->pair[$i];
                continue;
            }
            if ($k !== \__McTokId::T_FUNCTION) {
                continue;
            }
            $i++;
            if ($i < $n && self::eff($s, $i) === CT::RETURN_REF) {
                $i++;
            }
            if ($i < $n && self::eff($s, $i) === \__McTokId::T_STRING) {
                $out[] = $i;
            }
        }
        return $out;
    }
}
