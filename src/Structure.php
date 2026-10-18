<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Structural columns of one Source, built once in a single pass (plus one pass for statements) from the ORIGINAL token
 * stream and Source::$ct / Source::$pair (see Transform). All columns are flat int[] indexed by token. Token kinds never
 * change, so everything here stays valid for the whole gap phase; nothing here reads a gap. For anything that depends on
 * the current gaps (first on its line, indentation) use Source::isLineStart / Source::lineIndent, which read the live gaps.
 *
 * - pair[i]     matching bracket/brace/paren of an opener or closer, -1 otherwise (Source::$pair, shared)
 * - depth[i]    number of enclosing brackets; an opener and its closer share one value
 * - parent[i]   index of the innermost enclosing opener, -1 at top level; an opener and its closer share one value
 * - owner[i]    OW_* of an opener AND its closer: what the bracket belongs to (class body, parameter list, array, ...)
 * - sep[i]      argument/element boundaries. For an opener: the first top-level comma inside it, or its closer when there
 *               is none. For a comma: the next comma at the same level, or the closer. Iterate the elements of the bracket
 *               at `$o` with `for ($c = $st->sep[$o]; ; $c = $st->sep[$c]) { ...element ends before $c...; if ($c === $st->pair[$o]) break; }`.
 *               Defined for the commas directly inside any bracket (so also `{` of a match, `use A, B;` in a class body);
 *               a comma outside every bracket has sep -1.
 * - stmtStart[i] / stmtEnd[i]  statement SEGMENT of the token (inclusive range). A segment ends at `;` (outside parens/
 *               brackets), after a `{` that opens a block (the header), at a block `}` (a one-token segment), after a
 *               `case X:` / `default:` label, a goto label, an alternative-syntax colon (`if (..):`, `else:`), and at
 *               T_OPEN_TAG, T_CLOSE_TAG and T_INLINE_HTML (one segment each, close tag ends the one it follows).
 *               Expression braces (`->{expr}`, `${..}`, `{$x}`, group import, property-less CT braces) do not split.
 *               A match arm list is one segment between the `{` and `}` of the match. stmtStart[i] === i marks a statement start.
 */
final class Structure
{
    public const OW_NONE = 0;
    // braces
    public const OW_CLASS_BODY = 1;
    public const OW_FUNC_BODY = 2;
    public const OW_CLOSURE_BODY = 3;
    /** if / elseif / else / while / for / foreach / switch / do / try / catch / finally / declare */
    public const OW_CONTROL = 4;
    public const OW_MATCH = 5;
    public const OW_NAMESPACE = 6;
    /** a bare block, or one the walk back could not classify (also the body of a property hook accessor) */
    public const OW_BLOCK = 7;
    /** `use A, B { A::x as y; }` */
    public const OW_TRAIT_ADAPT = 8;
    public const OW_PROPERTY_HOOK = 9;
    public const OW_GROUP_IMPORT = 10;
    /** `->{expr}`, `${expr}` variable-variable, `Foo::{expr}` */
    public const OW_DYN_BRACE = 11;
    /** `{$x}` and `${x}` inside a string */
    public const OW_CURLY_STRING = 12;
    // parens
    /** function / method / closure / fn parameter list */
    public const OW_PARAMS = 20;
    /** call, `new X(..)`, `new class(..)`, attribute arguments */
    public const OW_ARGS = 21;
    /** if / elseif / while / for / foreach / switch / catch / declare condition */
    public const OW_CONTROL_PAREN = 22;
    public const OW_MATCH_PAREN = 23;
    /** closure `use (...)` */
    public const OW_USE_LAMBDA = 24;
    /** isset / unset / empty / exit / echo / print / eval / include / require followed by `(` */
    public const OW_LANG_CONSTRUCT = 25;
    /** `array(...)` */
    public const OW_ARRAY_LONG = 26;
    /** `list(...)` */
    public const OW_LIST = 27;
    /** grouping, `(new Foo)`, `new (expr)` */
    public const OW_GROUP = 28;
    /** DNF type group `(A&B)|C` */
    public const OW_TYPE_GROUP = 29;
    // brackets
    /** short array literal */
    public const OW_ARRAY = 40;
    /** short destructuring `[$a, $b] = ..` */
    public const OW_DESTRUCT = 41;
    /** `$a[0]` */
    public const OW_INDEX = 42;
    /** `#[...]` */
    public const OW_ATTRIBUTE = 43;

    /** @var int[] */
    public array $pair = [];
    /** @var int[] */
    public array $depth = [];
    /** @var int[] */
    public array $parent = [];
    /** @var int[] OW_* of every opener and closer, OW_NONE elsewhere */
    public array $owner = [];
    /** @var int[] */
    public array $sep = [];
    /** @var int[] */
    public array $stmtStart = [];
    /** @var int[] */
    public array $stmtEnd = [];

    private static function isControlKw(int $k): bool
    {
        return $k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_WHILE
            || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_TRY
            || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_FINALLY || $k === \__McTokId::T_DO || $k === \__McTokId::T_DECLARE;
    }

    /** `{` that opens a statement block (a statement segment boundary): plain braces and property hook braces */
    private static function isBlockBrace(Source $s, int $i): bool
    {
        return $s->kind[$i] === 123 && ($s->ct[$i] === 0 || $s->ct[$i] === CT::PROPERTY_HOOK_BRACE_OPEN);
    }

    /** OW_* of a plain `{`: walk back over the header (balanced groups are jumped) to the keyword that owns it. */
    private static function braceOwner(Source $s, int $i): int
    {
        $j = $i - 1;
        while ($j >= 0) {
            $k = $s->kind[$j];
            if ($k === 41 || $k === 93) {
                $p = $s->pair[$j];
                $j = $p >= 0 ? $p - 1 : $j - 1;
                continue;
            }
            if ($k === 59 || $k === 123 || $k === 125) {
                return self::OW_BLOCK;
            }
            if ($s->ct[$j] === 0) {
                if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                    return self::OW_CLASS_BODY;
                }
                if ($k === \__McTokId::T_FUNCTION) {
                    $nx = $j + 1;
                    if ($s->ct[$nx] === CT::RETURN_REF) {
                        $nx++;
                    }
                    return $s->kind[$nx] === \__McTokId::T_STRING ? self::OW_FUNC_BODY : self::OW_CLOSURE_BODY;
                }
                if ($k === \__McTokId::T_MATCH) {
                    return self::OW_MATCH;
                }
                if ($k === \__McTokId::T_NAMESPACE) {
                    return self::OW_NAMESPACE;
                }
                if (self::isControlKw($k)) {
                    return self::OW_CONTROL;
                }
            } elseif ($s->ct[$j] === CT::USE_TRAIT) {
                return self::OW_TRAIT_ADAPT;
            }
            $j--;
        }
        return self::OW_BLOCK;
    }

    /** OW_* of a `(`, from the token before it. */
    private static function parenOwner(Source $s, int $i): int
    {
        $c = $s->ct[$i];
        if ($c === CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN) {
            return self::OW_GROUP;
        }
        if ($c === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN) {
            return self::OW_TYPE_GROUP;
        }
        if ($i === 0) {
            return self::OW_GROUP;
        }
        $p = $i - 1;
        $k = $s->kind[$p];
        $pc = $s->ct[$p];
        if ($pc === CT::RETURN_REF || $pc === CT::USE_LAMBDA) {
            return $pc === CT::USE_LAMBDA ? self::OW_USE_LAMBDA : self::OW_PARAMS;
        }
        if ($pc !== 0) {
            // custom tokens that end an expression: `->{x}(..)`, `$$x(..)`, `Foo::{x}(..)`, `X::class(..)`
            if ($pc === CT::DYNAMIC_PROP_BRACE_CLOSE || $pc === CT::DYNAMIC_VAR_BRACE_CLOSE || $pc === CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE
                || $pc === CT::ARRAY_BRACKET_CLOSE || $pc === CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE || $pc === CT::NAMED_ARGUMENT_NAME
                || $pc === CT::CLASS_CONSTANT || $pc === CT::NAMESPACE_OPERATOR) {
                return self::OW_ARGS;
            }
            return self::OW_GROUP;
        }
        if ($k === \__McTokId::T_STRING) {
            if ($p >= 1) {
                $q = $p - 1;
                if (($s->kind[$q] === \__McTokId::T_FUNCTION && $s->ct[$q] === 0) || $s->ct[$q] === CT::RETURN_REF) {
                    return self::OW_PARAMS;
                }
            }
            return self::OW_ARGS;
        }
        if ($k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_FN) {
            return self::OW_PARAMS;
        }
        if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_FOR
            || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_DECLARE) {
            return self::OW_CONTROL_PAREN;
        }
        if ($k === \__McTokId::T_MATCH) {
            return self::OW_MATCH_PAREN;
        }
        if ($k === \__McTokId::T_ARRAY) {
            return self::OW_ARRAY_LONG;
        }
        if ($k === \__McTokId::T_LIST) {
            return self::OW_LIST;
        }
        if ($k === \__McTokId::T_ISSET || $k === \__McTokId::T_UNSET || $k === \__McTokId::T_EMPTY || $k === \__McTokId::T_EXIT
            || $k === \__McTokId::T_ECHO || $k === \__McTokId::T_PRINT || $k === \__McTokId::T_EVAL || $k === \__McTokId::T_INCLUDE
            || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE) {
            return self::OW_LANG_CONSTRUCT;
        }
        if ($k === \__McTokId::T_VARIABLE || $k === 41 || $k === 93 || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_CLASS) {
            return self::OW_ARGS;
        }
        return self::OW_GROUP;
    }

    public static function build(Source $s): Structure
    {
        $st = new Structure();
        $n = $s->n;
        $kind = $s->kind;
        $ct = $s->ct;
        $pair = $s->pair;
        $depth = [];
        $parent = [];
        $owner = [];
        $sep = [];
        /** @var int[] $lastSep per opener: the separator (or opener) whose sep still points at the closer */
        $lastSep = [];
        $cur = -1;
        $d = 0;
        for ($i = 0; $i < $n; $i++) {
            $p = $pair[$i];
            $k = $kind[$i];
            $ow = self::OW_NONE;
            $sv = -1;
            if ($p > $i) {
                $parent[] = $cur;
                $depth[] = $d;
                if ($k === 40) {
                    $ow = self::parenOwner($s, $i);
                } elseif ($k === 123) {
                    $c = $ct[$i];
                    if ($c === 0) {
                        $ow = self::braceOwner($s, $i);
                    } elseif ($c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                        $ow = self::OW_PROPERTY_HOOK;
                    } elseif ($c === CT::GROUP_IMPORT_BRACE_OPEN) {
                        $ow = self::OW_GROUP_IMPORT;
                    } else {
                        $ow = self::OW_DYN_BRACE;
                    }
                } elseif ($k === \__McTokId::T_CURLY_OPEN || $k === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES) {
                    $ow = self::OW_CURLY_STRING;
                } elseif ($k === \__McTokId::T_ATTRIBUTE) {
                    $ow = self::OW_ATTRIBUTE;
                } else {
                    $c = $ct[$i];
                    $ow = $c === CT::ARRAY_BRACKET_OPEN ? self::OW_ARRAY : ($c === CT::DESTRUCTURING_BRACKET_OPEN ? self::OW_DESTRUCT : self::OW_INDEX);
                }
                $sv = $p;
                $lastSep[$i] = $i;
                $cur = $i;
                $d++;
            } elseif ($p >= 0) {
                $d--;
                $cur = $parent[$p];
                $parent[] = $cur;
                $depth[] = $d;
                $ow = $owner[$p];
            } else {
                $parent[] = $cur;
                $depth[] = $d;
                if ($k === 44 && $cur >= 0) {
                    $sep[$lastSep[$cur]] = $i;
                    $lastSep[$cur] = $i;
                    $sv = $pair[$cur];
                }
            }
            $owner[] = $ow;
            $sep[] = $sv;
        }
        $st->pair = $pair;
        $st->depth = $depth;
        $st->parent = $parent;
        $st->owner = $owner;
        $st->sep = $sep;
        $st->segments($s);
        return $st;
    }

    private function segments(Source $s): void
    {
        $n = $s->n;
        $kind = $s->kind;
        $ct = $s->ct;
        $pair = $s->pair;
        $stmtStart = [];
        $stmtEnd = [];
        for ($i = 0; $i < $n; $i++) {
            $stmtStart[] = 0;
            $stmtEnd[] = 0;
        }
        /** @var int[] $pcStack paren count saved at each open block `{` */
        $pcStack = [];
        $fp = 0;
        $segStart = 0;
        $pc = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $kind[$i];
            $end = false;
            if ($k === 40 || $k === 91 || $k === \__McTokId::T_ATTRIBUTE) {
                $pc++;
            } elseif (($k === 41 || $k === 93) && $pc > 0) {
                $pc--;
            } elseif ($k === 123) {
                if (self::isBlockBrace($s, $i)) {
                    for ($j = $segStart; $j <= $i; $j++) {
                        $stmtStart[$j] = $segStart;
                        $stmtEnd[$j] = $i;
                    }
                    $segStart = $i + 1;
                    $pcStack[$fp] = $pc;
                    $fp++;
                    $pc = 0;
                }
            } elseif ($k === 125) {
                if ($pair[$i] < 0 || self::isBlockBrace($s, $pair[$i])) {
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
            } elseif ($pc === 0) {
                if ($k === 59 || $k === \__McTokId::T_CLOSE_TAG || $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO
                    || $k === \__McTokId::T_INLINE_HTML) {
                    $end = true;
                } elseif ($k === 58 && $ct[$i] === 0) {
                    $f = $kind[$segStart];
                    if ($f === \__McTokId::T_CASE || ($f === \__McTokId::T_DEFAULT && $i === $segStart + 1)) {
                        $end = true;
                    } elseif ($i === $segStart + 1 && $f === \__McTokId::T_STRING) {
                        $end = true; // goto label
                    } elseif ($i > 0 && ($kind[$i - 1] === \__McTokId::T_ELSE || ($kind[$i - 1] === 41 && $pair[$i - 1] > 0
                        && self::isControlKw($kind[$pair[$i - 1] - 1])))) {
                        $end = true; // alternative syntax
                    }
                }
            }
            if ($end) {
                for ($j = $segStart; $j <= $i; $j++) {
                    $stmtStart[$j] = $segStart;
                    $stmtEnd[$j] = $i;
                }
                $segStart = $i + 1;
            }
        }
        for ($j = $segStart; $j < $n; $j++) {
            $stmtStart[$j] = $segStart;
            $stmtEnd[$j] = $n - 1;
        }
        $this->stmtStart = $stmtStart;
        $this->stmtEnd = $stmtEnd;
    }
}
