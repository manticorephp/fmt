<?php

declare(strict_types=1);

namespace Fmt;

/** NamespacesAnalyzer::getDeclarations and NamespaceUsesAnalyzer (single, non-group imports) over a Source. */
final class NsAnalysis
{
    /**
     * Upstream's namespace scopes: [name, scope start, scope end] (token indexes; the end of an unbraced namespace is the token before the next
     * `namespace`, or the last token); no namespace at all: one global scope over the file.
     * @return array<int, array{0:string,1:int,2:int}>
     */
    public static function scopes(Source $s): array
    {
        $out = [];
        $n = $s->n;
        for ($i = 1; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_NAMESPACE || $s->ct[$i] !== 0) {
                continue;
            }
            $e = $i + 1;
            while ($e < $n && !(($s->kind[$e] === 59 || $s->kind[$e] === 123) && $s->ct[$e] === 0)) {
                $e++;
            }
            if ($e >= $n) {
                break;
            }
            $name = '';
            for ($j = $i + 1; $j < $e; $j++) {
                $name .= $s->gapBefore[$j] . $s->text($j);
            }
            $name = \trim($name);
            if ($s->kind[$e] === 123) {
                $end = $s->pair[$e];
            } else {
                $end = $n;
                for ($j = $e + 1; $j < $n; $j++) {
                    if ($s->kind[$j] === \__McTokId::T_NAMESPACE && $s->ct[$j] === 0) {
                        $end = $j;
                        break;
                    }
                }
                $end--;
            }
            $out[] = [$name, $i, $end];
            $i = $end;
        }
        if ($out === [] && $n > 0) {
            for ($i = 0; $i < $n; $i++) {
                if ($s->kind[$i] === \__McTokId::T_OPEN_TAG) {
                    $t = $s->kind[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
                    $out[] = ['', $t, $n - 1];
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Imports that are no part of a multi-import (`use A\B [as C];` with no comma / group): [type 0 class 1 function 2 const, full name, short name, use index, `;` index].
     * @return array<int, array{0:int,1:string,2:string,3:int,4:int}>
     */
    public static function singleImports(Source $s): array
    {
        $out = [];
        $n = $s->n;
        for ($u = 0; $u < $n; $u++) {
            if ($s->kind[$u] !== \__McTokId::T_USE || $s->ct[$u] !== 0) {
                continue;
            }
            $end = $u + 1;
            while ($end < $n && !(($s->kind[$end] === 59 && $s->ct[$end] === 0) || $s->kind[$end] === \__McTokId::T_CLOSE_TAG)) {
                $end++;
            }
            if ($end >= $n) {
                break;
            }
            $type = 0;
            $j = $u + 1;
            if ($s->ct[$j] === CT::FUNCTION_IMPORT) {
                $type = 1;
                $j++;
            } elseif ($s->ct[$j] === CT::CONST_IMPORT) {
                $type = 2;
                $j++;
            }
            $full = '';
            $last = '';
            $alias = null;
            $multi = false;
            for (; $j < $end; $j++) {
                $k = $s->kind[$j];
                $c = $s->ct[$j];
                if (($k === 44 && $c === 0) || $c === CT::GROUP_IMPORT_BRACE_OPEN) {
                    $multi = true;
                    break;
                }
                if ($k === \__McTokId::T_AS && $c === 0) {
                    $a = $j + 1;
                    $alias = $a < $end ? $s->text($a) : null;
                    break;
                }
                if (($k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR) && $c === 0) {
                    $full .= $s->text($j);
                    if ($k === \__McTokId::T_STRING) {
                        $last = $s->text($j);
                    }
                }
            }
            if ($multi || $last === '') {
                continue;
            }
            $out[] = [$type, $full, $alias ?? $last, $u, $end];
        }
        return $out;
    }

    /** AttributeAnalyzer::isAttribute */
    public static function isAttribute(Source $s, int $i): bool
    {
        $a = -1;
        for ($j = $i - 1; $j >= 0; $j--) {
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($c === CT::ATTRIBUTE_CLOSE) {
                break;
            }
            if ($c === 0 && ($k === 59 || $k === 123 || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_OPEN_TAG
                || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC
                || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_VARIABLE)) {
                if ($k === \__McTokId::T_ATTRIBUTE) {
                    $a = $j;
                }
                break;
            }
        }
        if ($a < 0) {
            return false;
        }
        $d = 0;
        for ($j = $a + 1; $j < $i; $j++) {
            if ($s->ct[$j] === 0) {
                if ($s->kind[$j] === 40) {
                    $d++;
                } elseif ($s->kind[$j] === 41) {
                    $d--;
                }
            }
        }
        return $d === 0;
    }

    /**
     * Effective ids of the block openers of Tokens::getBlockEdgeDefinitions (a plain char is its own ord, a custom token its CT id).
     * @return array<int, bool>
     */
    public static function blockStartIds(): array
    {
        $out = [];
        foreach ([40, 91, 123, CT::ARRAY_BRACKET_OPEN, CT::DYNAMIC_PROP_BRACE_OPEN, CT::DYNAMIC_VAR_BRACE_OPEN, CT::ARRAY_INDEX_BRACE_OPEN, CT::GROUP_IMPORT_BRACE_OPEN,
            CT::DESTRUCTURING_BRACKET_OPEN, CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN,
            CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN, \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES, CT::PROPERTY_HOOK_BRACE_OPEN, \__McTokId::T_ATTRIBUTE] as $k) {
            $out[$k] = true;
        }
        return $out;
    }

    /** @return array<int, bool> the closers */
    public static function blockEndIds(): array
    {
        $out = [];
        foreach ([41, 93, 125, CT::ARRAY_BRACKET_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE, CT::DYNAMIC_VAR_BRACE_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::GROUP_IMPORT_BRACE_CLOSE,
            CT::DESTRUCTURING_BRACKET_CLOSE, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE,
            CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE, CT::DOLLAR_CLOSE_CURLY_BRACES, CT::PROPERTY_HOOK_BRACE_CLOSE, CT::ATTRIBUTE_CLOSE] as $k) {
            $out[$k] = true;
        }
        return $out;
    }
}
