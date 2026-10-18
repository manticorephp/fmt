<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Port of php-cs-fixer's Tokenizer\Transformer\* as ONE pass over an immutable token stream: fills Source::$ct (0 = no
 * custom kind, else a CT::* id) and Source::$pair (matching bracket/brace/paren of every opener and closer, -1 else).
 * kind[] is never touched. Trivia is not a token of the model, so "next meaningful token" is simply i + 1, and upstream's
 * raw `$tokens[$index + 1]` (whitespace counts there) is "i + 1 AND gapBefore[i + 1] === ''".
 *
 * The transformers run in upstream's order (priority desc, then registration order) and read the output of earlier ones,
 * so a working column e[] holds the current effective kind of every token (kind[] until a transformer retypes it, then
 * the CT id): every `equals(...)`/`isGivenKind(...)` of upstream is a comparison on e[]. A transformer only retypes
 * tokens of its own candidate kind, so each walks a list of candidate indices (collected in the one scan below) instead of
 * every token; the lists are ascending, which keeps upstream's in-loop dependence (a token retyped by an earlier iteration
 * is seen retyped by a later one).
 *
 * T_NAME_QUALIFIED / FULLY_QUALIFIED / RELATIVE are the one transformer that changes token boundaries (NameQualified
 * splits them into T_NS_SEPARATOR / T_STRING / T_NAMESPACE): that happens in Source::parse, before this pass.
 */
final class Transform
{
    private const G_ATTR = 1;
    private const G_ARRAY = 2;
    private const G_AMP = 3;
    private const G_CF = 4;
    private const G_ELLIPSIS = 5;
    private const G_NS = 6;
    private const G_LBRACE = 7;
    private const G_CURLY_OPEN = 8;
    private const G_DOLLAR_OPEN = 9;
    private const G_DOLLAR = 10;
    private const G_OBJOP = 11;
    private const G_DCOLON = 12;
    private const G_LBRACKET = 13;
    private const G_NEW = 14;
    private const G_USEC = 15;
    private const G_COLON = 16;
    private const G_PIPE = 17;
    private const G_QM = 18;
    private const G_LPAREN = 19;
    private const G_RPAREN = 20;
    private const G_RBRACKET = 21;
    private const G_RBRACE = 22;
    private const G_USE = 23;

    /** @var int[] token kind => G_* (0 = no transformer cares) */
    private static array $grp = [];

    private Source $src;
    private int $n = 0;
    /** @var int[] effective kind: kind[] until a transformer retypes the token */
    private array $e = [];
    /** @var int[] */
    private array $pair = [];
    /** @var int[] indices retyped TYPE_ALTERNATION, ascending */
    private array $alts = [];

    public static function run(Source $s): void
    {
        if (self::$grp === []) {
            self::$grp = self::groupTable();
        }
        $t = new Transform();
        $t->src = $s;
        $t->go();
    }

    /** @return int[] */
    private static function groupTable(): array
    {
        $g = \array_fill(0, 512, 0);
        $g[\__McTokId::T_ATTRIBUTE] = self::G_ATTR;
        $g[\__McTokId::T_ARRAY] = self::G_ARRAY;
        $g[\__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG] = self::G_AMP;
        $g[\__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG] = self::G_AMP;
        $g[\__McTokId::T_CONST] = self::G_CF;
        $g[\__McTokId::T_FUNCTION] = self::G_CF;
        $g[\__McTokId::T_ELLIPSIS] = self::G_ELLIPSIS;
        $g[\__McTokId::T_NAMESPACE] = self::G_NS;
        $g[123] = self::G_LBRACE;
        $g[\__McTokId::T_CURLY_OPEN] = self::G_CURLY_OPEN;
        $g[\__McTokId::T_DOLLAR_OPEN_CURLY_BRACES] = self::G_DOLLAR_OPEN;
        $g[36] = self::G_DOLLAR;
        $g[\__McTokId::T_OBJECT_OPERATOR] = self::G_OBJOP;
        $g[\__McTokId::T_NULLSAFE_OBJECT_OPERATOR] = self::G_OBJOP;
        $g[\__McTokId::T_DOUBLE_COLON] = self::G_DCOLON;
        $g[91] = self::G_LBRACKET;
        $g[\__McTokId::T_NEW] = self::G_NEW;
        $g[\__McTokId::T_CLASS] = self::G_USEC;
        $g[\__McTokId::T_TRAIT] = self::G_USEC;
        $g[\__McTokId::T_ENUM] = self::G_USEC;
        $g[\__McTokId::T_USE] = self::G_USE;
        $g[58] = self::G_COLON;
        $g[124] = self::G_PIPE;
        $g[63] = self::G_QM;
        $g[40] = self::G_LPAREN;
        $g[41] = self::G_RPAREN;
        $g[93] = self::G_RBRACKET;
        $g[125] = self::G_RBRACE;
        return $g;
    }

    private function go(): void
    {
        $s = $this->src;
        $n = $s->n;
        $this->n = $n;
        $kind = $s->kind;
        $grp = self::$grp;
        /** @var int[] $pair */
        $pair = \array_fill(0, $n, -1);
        /** @var int[] $stack */
        $stack = [];
        $sp = 0;
        /** @var int[] $lAttr */
        $lAttr = [];
        /** @var int[] $lArray */
        $lArray = [];
        /** @var int[] $lAmp */
        $lAmp = [];
        /** @var int[] $lCf */
        $lCf = [];
        /** @var int[] $lEll */
        $lEll = [];
        /** @var int[] $lNs */
        $lNs = [];
        /** @var int[] $lBrace */
        $lBrace = [];
        /** @var int[] $lDc */
        $lDc = [];
        /** @var int[] $lSq */
        $lSq = [];
        /** @var int[] $lNew */
        $lNew = [];
        /** @var int[] $lUsec merged T_USE + class-likes, ascending */
        $lUsec = [];
        /** @var int[] $lUse */
        $lUse = [];
        /** @var int[] $lColon */
        $lColon = [];
        /** @var int[] $lPipe */
        $lPipe = [];
        /** @var int[] $lQm */
        $lQm = [];
        for ($i = 0; $i < $n; $i++) {
            $k = $kind[$i];
            if ($k >= 512) {
                continue;
            }
            $g = $grp[$k];
            if ($g === 0) {
                continue;
            }
            if ($g === self::G_LPAREN) {
                $stack[$sp++] = $i;
            } elseif ($g === self::G_RPAREN) {
                if ($sp > 0 && $kind[$stack[$sp - 1]] === 40) {
                    $o = $stack[--$sp];
                    $pair[$o] = $i;
                    $pair[$i] = $o;
                }
            } elseif ($g === self::G_RBRACE) {
                if ($sp > 0 && self::isBraceOpen($kind[$stack[$sp - 1]])) {
                    $o = $stack[--$sp];
                    $pair[$o] = $i;
                    $pair[$i] = $o;
                }
            } elseif ($g === self::G_RBRACKET) {
                if ($sp > 0 && ($kind[$stack[$sp - 1]] === 91 || $kind[$stack[$sp - 1]] === \__McTokId::T_ATTRIBUTE)) {
                    $o = $stack[--$sp];
                    $pair[$o] = $i;
                    $pair[$i] = $o;
                }
            } elseif ($g === self::G_LBRACE) {
                $stack[$sp++] = $i;
                $lBrace[] = $i;
            } elseif ($g === self::G_CURLY_OPEN || $g === self::G_DOLLAR_OPEN) {
                $stack[$sp++] = $i;
                $lBrace[] = $i;
            } elseif ($g === self::G_LBRACKET) {
                $stack[$sp++] = $i;
                $lSq[] = $i;
            } elseif ($g === self::G_ATTR) {
                $stack[$sp++] = $i;
                $lAttr[] = $i;
            } elseif ($g === self::G_DOLLAR || $g === self::G_OBJOP) {
                $lBrace[] = $i;
            } elseif ($g === self::G_COLON) {
                $lColon[] = $i;
            } elseif ($g === self::G_QM) {
                $lQm[] = $i;
            } elseif ($g === self::G_CF) {
                $lCf[] = $i;
            } elseif ($g === self::G_AMP) {
                $lAmp[] = $i;
            } elseif ($g === self::G_PIPE) {
                $lPipe[] = $i;
            } elseif ($g === self::G_DCOLON) {
                $lDc[] = $i;
            } elseif ($g === self::G_USEC) {
                $lUsec[] = $i;
            } elseif ($g === self::G_USE) {
                $lUsec[] = $i;
                $lUse[] = $i;
            } elseif ($g === self::G_NEW) {
                $lNew[] = $i;
            } elseif ($g === self::G_ARRAY) {
                $lArray[] = $i;
            } elseif ($g === self::G_ELLIPSIS) {
                $lEll[] = $i;
            } elseif ($g === self::G_NS) {
                $lNs[] = $i;
            }
        }
        $this->pair = $pair;
        $s->pair = $pair;
        $this->e = $kind;

        // upstream order: priority desc, then registration order
        $this->attribute($lAttr);
        $this->arrayTypehint($lArray);
        $this->returnRef($lAmp);
        $this->firstClassCallable($lEll);
        $this->namespaceOperator($lNs);
        $this->brace($lBrace);
        $this->classConstant($lDc);
        $this->constructorPromotion($lCf);
        $this->importT($lCf);
        $this->squareBrace($lSq);
        $this->braceClassInstantiation($lNew);
        $this->useT($lUsec, $lUse);
        $this->typeColon($lColon);
        $this->typeAmpOrPipe($lAmp, 410);
        $this->typeAmpOrPipe($lPipe, 124);
        $this->namedArgument($lColon);
        $this->dnf();
        $this->nullable($lQm);

        $e = $this->e;
        $ct = [];
        for ($i = 0; $i < $n; $i++) {
            $ct[] = $e[$i] === $kind[$i] ? 0 : $e[$i];
        }
        $s->ct = $ct;
    }

    private static function isBraceOpen(int $k): bool
    {
        return $k === 123 || $k === \__McTokId::T_CURLY_OPEN || $k === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES;
    }

    /** equals('&'): the plain character or one of the two ampersand ids */
    private static function isAmp(int $v): bool
    {
        return $v === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $v === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG || $v === 38;
    }

    /** @param int[] $l */
    private function attribute(array $l): void
    {
        foreach ($l as $i) {
            $c = $this->pair[$i];
            if ($c >= 0) {
                $this->e[$c] = CT::ATTRIBUTE_CLOSE;
            }
        }
    }

    /** @param int[] $l */
    private function arrayTypehint(array $l): void
    {
        foreach ($l as $i) {
            if ($i + 1 >= $this->n || $this->e[$i + 1] !== 40) {
                $this->e[$i] = CT::ARRAY_TYPEHINT;
            }
        }
    }

    /** @param int[] $l */
    private function returnRef(array $l): void
    {
        foreach ($l as $i) {
            if ($i > 0) {
                $p = $this->e[$i - 1];
                if ($p === \__McTokId::T_FUNCTION || $p === \__McTokId::T_FN) {
                    $this->e[$i] = CT::RETURN_REF;
                }
            }
        }
    }

    /** @param int[] $l */
    private function firstClassCallable(array $l): void
    {
        foreach ($l as $i) {
            if ($i > 0 && $i + 1 < $this->n && $this->e[$i - 1] === 40 && $this->e[$i + 1] === 41) {
                $this->e[$i] = CT::FIRST_CLASS_CALLABLE;
            }
        }
    }

    /** @param int[] $l */
    private function namespaceOperator(array $l): void
    {
        foreach ($l as $i) {
            if ($i + 1 < $this->n && $this->e[$i + 1] === \__McTokId::T_NS_SEPARATOR) {
                $this->e[$i] = CT::NAMESPACE_OPERATOR;
            }
        }
    }

    /** @param int[] $l */
    private function classConstant(array $l): void
    {
        $s = $this->src;
        foreach ($l as $i) {
            $j = $i + 1;
            if ($j >= $this->n) {
                continue;
            }
            $v = $this->e[$j];
            if ($v === \__McTokId::T_CLASS) {
                $this->e[$j] = CT::CLASS_CONSTANT;
            } elseif ($v === \__McTokId::T_STRING && $s->end[$j] - $s->start[$j] === 5 && \strcasecmp($s->text($j), 'class') === 0) {
                $this->e[$j] = CT::CLASS_CONSTANT;
            }
        }
    }

    /** @param int[] $l */
    private function constructorPromotion(array $l): void
    {
        $s = $this->src;
        foreach ($l as $i) {
            if ($this->e[$i] !== \__McTokId::T_FUNCTION || $i + 2 >= $this->n) {
                continue;
            }
            $f = $i + 1;
            if ($this->e[$f] !== \__McTokId::T_STRING || $s->end[$f] - $s->start[$f] !== 11 || \strtolower($s->text($f)) !== '__construct') {
                continue;
            }
            $o = $f + 1;
            if ($this->e[$o] !== 40 || $this->pair[$o] < 0) {
                continue;
            }
            $c = $this->pair[$o];
            for ($a = $o; $a < $c; $a++) {
                $v = $this->e[$a];
                if ($v === \__McTokId::T_PUBLIC) {
                    $this->e[$a] = CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC;
                } elseif ($v === \__McTokId::T_PROTECTED) {
                    $this->e[$a] = CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED;
                } elseif ($v === \__McTokId::T_PRIVATE) {
                    $this->e[$a] = CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE;
                }
            }
        }
    }

    /** @param int[] $l */
    private function importT(array $l): void
    {
        foreach ($l as $i) {
            $v = $this->e[$i];
            if ($i === 0) {
                continue;
            }
            if ($this->e[$i - 1] !== \__McTokId::T_USE) {
                $j = $i + 1;
                $found = -1;
                while ($j < $this->n) {
                    $w = $this->e[$j];
                    if ($w === 61 || $w === 40 || $w === CT::RETURN_REF || $w === CT::GROUP_IMPORT_BRACE_CLOSE) {
                        $found = $w;
                        break;
                    }
                    $j++;
                }
                if ($found !== CT::GROUP_IMPORT_BRACE_CLOSE) {
                    continue;
                }
            }
            $this->e[$i] = $v === \__McTokId::T_FUNCTION ? CT::FUNCTION_IMPORT : CT::CONST_IMPORT;
        }
    }

    /** Upstream naivelyFindCurlyBlockEnd: counts tokens by their CONTENT being exactly "{" / "}". */
    private function naiveCurlyEnd(int $open): int
    {
        $s = $this->src;
        $level = 1;
        $last = $this->n - 1;
        for ($j = $open + 1; $j !== $last; $j++) {
            if ($s->end[$j] - $s->start[$j] !== 1) {
                continue;
            }
            $b = \ord($s->code[$s->start[$j]]);
            if ($b === 123) {
                $level++;
            } elseif ($b === 125) {
                $level--;
                if ($level === 0) {
                    return $j;
                }
            }
        }
        return -1;
    }

    /** @param int[] $l */
    private function brace(array $l): void
    {
        $s = $this->src;
        $n = $this->n;
        foreach ($l as $i) {
            $v = $this->e[$i];
            if ($v === \__McTokId::T_CURLY_OPEN) {
                $level = 1;
                $j = $i;
                do {
                    $j++;
                    if ($j >= $n) {
                        break;
                    }
                    $w = $this->e[$j];
                    if ($w === 123 || $w === \__McTokId::T_CURLY_OPEN) {
                        $level++;
                    } elseif ($w === 125) {
                        $level--;
                    }
                } while ($level > 0);
                if ($level === 0) {
                    $this->e[$j] = CT::CURLY_CLOSE;
                }
            }
            if ($v === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($this->e[$j] === 125) {
                        $this->e[$j] = CT::DOLLAR_CLOSE_CURLY_BRACES;
                        break;
                    }
                }
            }
            if (($v === \__McTokId::T_OBJECT_OPERATOR || $v === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) && $i + 1 < $n
                && $s->gapBefore[$i + 1] === '' && $this->e[$i + 1] === 123) {
                $c = $this->naiveCurlyEnd($i + 1);
                if ($c >= 0) {
                    $this->e[$i + 1] = CT::DYNAMIC_PROP_BRACE_OPEN;
                    $this->e[$c] = CT::DYNAMIC_PROP_BRACE_CLOSE;
                }
            }
            if ($v === 36 && $i + 1 < $n && $this->e[$i + 1] === 123) {
                $c = $this->naiveCurlyEnd($i + 1);
                if ($c >= 0) {
                    $this->e[$i + 1] = CT::DYNAMIC_VAR_BRACE_OPEN;
                    $this->e[$c] = CT::DYNAMIC_VAR_BRACE_CLOSE;
                }
            }
            if ($this->e[$i] === 123) {
                $this->propertyHook($i);
            }
            if ($this->e[$i] === 123) {
                if ($i > 0 && $this->e[$i - 1] === \__McTokId::T_NS_SEPARATOR) {
                    $c = $this->naiveCurlyEnd($i);
                    if ($c >= 0) {
                        $this->e[$i] = CT::GROUP_IMPORT_BRACE_OPEN;
                        $this->e[$c] = CT::GROUP_IMPORT_BRACE_CLOSE;
                    }
                }
            }
            if ($this->e[$i] === 123) {
                $this->dynamicClassConstantFetch($i);
            }
        }
    }

    private function propertyHook(int $i): void
    {
        $s = $this->src;
        $n = $this->n;
        $j = $i + 1;
        while ($j < $n && $this->e[$j] === \__McTokId::T_ATTRIBUTE) {
            $c = $this->pair[$j];
            if ($c < 0) {
                return;
            }
            $j = $c + 1;
        }
        if ($j >= $n || $this->e[$j] !== \__McTokId::T_STRING || $s->end[$j] - $s->start[$j] !== 3) {
            return;
        }
        $t = \strtolower($s->text($j));
        if ($t !== 'get' && $t !== 'set') {
            return;
        }
        $m = $j + 1;
        if ($m >= $n) {
            return;
        }
        $w = $this->e[$m];
        if ($w !== 40 && $w !== 123 && $w !== 59 && $w !== \__McTokId::T_DOUBLE_ARROW) {
            return;
        }
        if ($w === 40) {
            $c = $this->pair[$m];
            if ($c < 0 || $c + 1 >= $n) {
                return;
            }
            $a = $this->e[$c + 1];
            if ($a !== 123 && $a !== \__McTokId::T_DOUBLE_ARROW) {
                return;
            }
        }
        $c = $this->naiveCurlyEnd($i);
        if ($c >= 0) {
            $this->e[$i] = CT::PROPERTY_HOOK_BRACE_OPEN;
            $this->e[$c] = CT::PROPERTY_HOOK_BRACE_CLOSE;
        }
    }

    private function dynamicClassConstantFetch(int $i): void
    {
        $p = $i - 1;
        while ($p >= 0 && $this->e[$p] !== \__McTokId::T_DOUBLE_COLON) {
            if ($this->e[$p] !== 41 || $this->pair[$p] < 0) {
                return;
            }
            $p = $this->pair[$p] - 1;
            if ($p < 0 || $this->e[$p] !== 125 || $this->pair[$p] < 0) {
                return;
            }
            $p = $this->pair[$p] - 1;
        }
        if ($p < 0) {
            return;
        }
        $c = $this->naiveCurlyEnd($i);
        if ($c < 0 || $c + 1 >= $this->n) {
            return;
        }
        $a = $this->e[$c + 1];
        if ($a !== 59 && $a !== \__McTokId::T_CLOSE_TAG && $a !== \__McTokId::T_DOUBLE_COLON) {
            return;
        }
        $this->e[$i] = CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN;
        $this->e[$c] = CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE;
    }

    /** prev token of '[' that rules out an array literal/destructuring (isArrayDestructing's list) */
    private static function indexable(int $v): bool
    {
        return $v === 41 || $v === 93 || $v === 34 || $v === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $v === \__McTokId::T_STRING
            || $v === \__McTokId::T_STRING_VARNAME || $v === \__McTokId::T_VARIABLE || $v === CT::ARRAY_BRACKET_CLOSE
            || $v === CT::DYNAMIC_PROP_BRACE_CLOSE || $v === CT::DYNAMIC_VAR_BRACE_CLOSE || $v === CT::ARRAY_INDEX_BRACE_CLOSE;
    }

    private function isArrayDestructing(int $i): bool
    {
        if ($this->e[$i] !== 91 || $i === 0) {
            return false;
        }
        $pi = $i - 1;
        $pv = $this->e[$pi];
        if (self::indexable($pv)) {
            return false;
        }
        if ($pv === \__McTokId::T_AS) {
            return true;
        }
        if ($pv === \__McTokId::T_DOUBLE_ARROW) {
            $vi = $pi - 1;
            if ($vi < 0 || $this->e[$vi] !== \__McTokId::T_VARIABLE) {
                return false;
            }
            if ($vi - 1 >= 0 && $this->e[$vi - 1] === \__McTokId::T_AS) {
                return true;
            }
        }
        $end = $this->pair[$i];
        return $end >= 0 && $end + 1 < $this->n && $this->e[$end + 1] === 61;
    }

    private function isShortArray(int $i): bool
    {
        if ($this->e[$i] !== 91 || $i === 0) {
            return false;
        }
        $pv = $this->e[$i - 1];
        if ($pv === 125 || self::indexable($pv)) {
            return false;
        }
        if ($i + 1 < $this->n && $this->e[$i + 1] === 93) {
            return true;
        }
        return !$this->isArrayDestructing($i);
    }

    /** @param int[] $l */
    private function squareBrace(array $l): void
    {
        foreach ($l as $i) {
            if ($this->isArrayDestructing($i)) {
                $end = $this->pair[$i];
                if ($end < 0) {
                    continue;
                }
                $this->e[$i] = CT::DESTRUCTURING_BRACKET_OPEN;
                $this->e[$end] = CT::DESTRUCTURING_BRACKET_CLOSE;
                $prev = $i;
                for ($j = $i + 1; $j < $end; $j++) {
                    $pv = $this->e[$prev];
                    if ($this->e[$j] === 91 && ($pv === CT::DESTRUCTURING_BRACKET_OPEN || $pv === 44) && $this->pair[$j] >= 0) {
                        $this->e[$this->pair[$j]] = CT::DESTRUCTURING_BRACKET_CLOSE;
                        $this->e[$j] = CT::DESTRUCTURING_BRACKET_OPEN;
                    }
                    $prev = $j;
                }
                continue;
            }
            if ($this->isShortArray($i)) {
                $end = $this->pair[$i];
                if ($end >= 0) {
                    $this->e[$i] = CT::ARRAY_BRACKET_OPEN;
                    $this->e[$end] = CT::ARRAY_BRACKET_CLOSE;
                }
            }
        }
    }

    /** @param int[] $l */
    private function braceClassInstantiation(array $l): void
    {
        foreach ($l as $j) {
            $p = $j - 1;
            if ($p < 1 || $this->e[$p] !== 40 || $this->pair[$p] < 0) {
                continue;
            }
            $v = $this->e[$p - 1];
            if ($v === 41 || $v === 93 || $v === CT::ARRAY_INDEX_BRACE_CLOSE || $v === CT::ARRAY_BRACKET_CLOSE
                || $v === CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE || $v === \__McTokId::T_ARRAY || $v === \__McTokId::T_CLASS
                || $v === \__McTokId::T_ELSEIF || $v === \__McTokId::T_FOR || $v === \__McTokId::T_FOREACH || $v === \__McTokId::T_IF
                || $v === \__McTokId::T_STATIC || $v === \__McTokId::T_STRING || $v === \__McTokId::T_SWITCH
                || $v === \__McTokId::T_VARIABLE || $v === \__McTokId::T_WHILE) {
                continue;
            }
            $this->e[$p] = CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN;
            $this->e[$this->pair[$p]] = CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE;
        }
    }

    /**
     * @param int[] $l T_USE + class-likes, ascending
     * @param int[] $uses T_USE only, ascending
     */
    private function useT(array $l, array $uses): void
    {
        $n = $this->n;
        $nu = \count($uses);
        $up = 0;
        $scanned = -1;
        foreach ($l as $i) {
            $v = $this->e[$i];
            if ($v === \__McTokId::T_USE) {
                if ($i + 1 < $n && $this->e[$i + 1] === 40) {
                    $this->e[$i] = CT::USE_LAMBDA;
                }
                continue;
            }
            if ($v === \__McTokId::T_CLASS && $i > 0 && $this->e[$i - 1] === \__McTokId::T_DOUBLE_COLON) {
                continue;
            }
            if ($v !== \__McTokId::T_CLASS && $v !== \__McTokId::T_TRAIT && $v !== \__McTokId::T_ENUM) {
                continue;
            }
            $o = $i + 1;
            while ($o < $n && $this->e[$o] !== 123) {
                $o++;
            }
            if ($o >= $n || $this->pair[$o] < 0) {
                continue;
            }
            $limit = $this->pair[$o];
            if ($o < $scanned) {
                continue; // inside a body already scanned: every T_USE there is typed
            }
            $scanned = $limit;
            while ($up < $nu && $uses[$up] < $o) {
                $up++;
            }
            while ($up < $nu && $uses[$up] < $limit) {
                $u = $uses[$up];
                if ($this->e[$u] === \__McTokId::T_USE) {
                    $this->e[$u] = ($u + 1 < $n && $this->e[$u + 1] === 40) ? CT::USE_LAMBDA : CT::USE_TRAIT;
                }
                $up++;
            }
        }
    }

    /** @param int[] $l */
    private function typeColon(array $l): void
    {
        foreach ($l as $i) {
            if ($this->e[$i] !== 58 || $i < 1) {
                continue;
            }
            $end = $i - 1;
            if ($end >= 1 && $this->e[$end - 1] === \__McTokId::T_ENUM) {
                $this->e[$i] = CT::TYPE_COLON;
                continue;
            }
            if ($this->e[$end] !== 41 || $this->pair[$end] < 0) {
                continue;
            }
            $p = $this->pair[$end] - 1;
            if ($p < 0) {
                continue;
            }
            $v = $this->e[$p];
            if ($v === \__McTokId::T_STRING) {
                $p--;
                if ($p < 0) {
                    continue;
                }
                $v = $this->e[$p];
            }
            if ($v === \__McTokId::T_FUNCTION || $v === CT::RETURN_REF || $v === CT::USE_LAMBDA || $v === \__McTokId::T_FN) {
                $this->e[$i] = CT::TYPE_COLON;
            }
        }
    }

    /** TYPE_TOKENS of AbstractTypeTransformer (trivia never appears) */
    private static function typeTok(int $v): bool
    {
        return $v === \__McTokId::T_STRING || $v === \__McTokId::T_NS_SEPARATOR || $v === 124 || self::isAmp($v) || $v === 40 || $v === 41
            || $v === \__McTokId::T_CALLABLE || $v === \__McTokId::T_STATIC || $v === CT::ARRAY_TYPEHINT
            || $v === CT::TYPE_ALTERNATION || $v === CT::TYPE_INTERSECTION;
    }

    private static function typeEnd(int $v): bool
    {
        return $v === 41 || $v === \__McTokId::T_CALLABLE || $v === \__McTokId::T_NS_SEPARATOR || $v === \__McTokId::T_STATIC
            || $v === \__McTokId::T_STRING || $v === CT::ARRAY_TYPEHINT;
    }

    private function isPartOfType(int $i): bool
    {
        $n = $this->n;
        $b = $i - 1;
        while ($b >= 0 && self::typeTok($this->e[$b])) {
            $b--;
        }
        if ($b >= 0) {
            $v = $this->e[$b];
            if ($v === \__McTokId::T_CATCH || $v === CT::TYPE_COLON || $v === \__McTokId::T_CONST) {
                return true;
            }
        }
        $a = $i + 1;
        while ($a < $n && self::typeTok($this->e[$a])) {
            $a++;
        }
        if ($a >= $n) {
            return false;
        }
        if ($this->e[$a] === \__McTokId::T_ELLIPSIS) {
            return true;
        }
        if ($this->e[$a] !== \__McTokId::T_VARIABLE) {
            return false;
        }
        $bv = $a - 1;
        if (self::isAmp($this->e[$bv])) {
            $p = $i - 1;
            while ($p >= 0) {
                $v = $this->e[$p];
                if ($v === 123 || $v === 125 || $v === 59 || $v === \__McTokId::T_CLOSE_TAG || $v === \__McTokId::T_FN || $v === \__McTokId::T_FUNCTION) {
                    return $v === \__McTokId::T_FN || $v === \__McTokId::T_FUNCTION;
                }
                $p--;
            }
            return false;
        }
        return self::typeEnd($this->e[$bv]);
    }

    /** @param int[] $l candidate indices of '&' (id 410) or '|' */
    private function typeAmpOrPipe(array $l, int $want): void
    {
        foreach ($l as $i) {
            if ($this->e[$i] === $want && $this->isPartOfType($i)) {
                $this->e[$i] = $want === 124 ? CT::TYPE_ALTERNATION : CT::TYPE_INTERSECTION;
                if ($want === 124) {
                    $this->alts[] = $i;
                }
            }
        }
    }

    /** @param int[] $l */
    private function namedArgument(array $l): void
    {
        foreach ($l as $i) {
            if ($this->e[$i] !== 58 || $i < 2) {
                continue;
            }
            $si = $i - 1;
            if ($this->e[$si] !== \__McTokId::T_STRING) {
                continue;
            }
            $v = $this->e[$si - 1];
            if ($v !== 44 && $v !== 40) {
                continue;
            }
            $this->e[$si] = CT::NAMED_ARGUMENT_NAME;
            $this->e[$i] = CT::NAMED_ARGUMENT_COLON;
        }
    }

    private function dnf(): void
    {
        $n = $this->n;
        foreach ($this->alts as $i) {
            if ($i + 1 < $n && $this->e[$i + 1] === 40 && $this->pair[$i + 1] >= 0) {
                $this->e[$i + 1] = CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN;
                $this->e[$this->pair[$i + 1]] = CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE;
            }
            if ($i > 0 && $this->e[$i - 1] === 41 && $this->pair[$i - 1] >= 0) {
                $this->e[$i - 1] = CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE;
                $this->e[$this->pair[$i - 1]] = CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN;
            }
        }
    }

    /** @param int[] $l */
    private function nullable(array $l): void
    {
        foreach ($l as $i) {
            if ($this->e[$i] !== 63 || $i < 1) {
                continue;
            }
            $v = $this->e[$i - 1];
            if ($v === 40 || $v === 44 || $v === CT::TYPE_COLON || $v === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC
                || $v === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $v === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE
                || $v === CT::ATTRIBUTE_CLOSE || $v === \__McTokId::T_PRIVATE || $v === \__McTokId::T_PROTECTED
                || $v === \__McTokId::T_PUBLIC || $v === \__McTokId::T_VAR || $v === \__McTokId::T_STATIC || $v === \__McTokId::T_CONST
                || $v === \__McTokId::T_ABSTRACT || $v === \__McTokId::T_FINAL || $v === \__McTokId::T_READONLY
                || $v === \__McTokId::T_PRIVATE_SET || $v === \__McTokId::T_PROTECTED_SET || $v === \__McTokId::T_PUBLIC_SET) {
                if ($v === \__McTokId::T_STATIC && $i >= 2 && $this->e[$i - 2] === \__McTokId::T_INSTANCEOF) {
                    continue;
                }
                $this->e[$i] = CT::NULLABLE_TYPE;
            }
        }
    }
}
