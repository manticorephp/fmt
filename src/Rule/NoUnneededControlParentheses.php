<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\NsAnalysis;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoUnneededControlParenthesesFixer (priority 30). `return ($a);`, `echo ($a);`, `clone ($b)`, `$x = ($y);`,
 * `(X) + (Y)` sequences ... lose a pair of parentheses around an expression that holds no operation, for the configured statements. Ported on the Source token
 * columns: tokens upstream clears (their whitespace merges into the neighbours: the token unit is deleted) or replaces by one space (when
 * nothing separates it from both neighbours) go through `gone` / `rep`; every lookup skips them as upstream's cleared tokens are skipped.
 */
final class NoUnneededControlParentheses extends EditRule
{
    /** @var array<int, bool> removed tokens */
    private array $gone = [];
    /** @var array<int, bool> tokens replaced by one space */
    private array $rep = [];
    private Source $s;
    /** @var array<int, bool> */
    private array $negPre = [];
    /** @var array<int, bool> */
    private array $before = [];
    /** @var array<int, bool> */
    private array $noop = [];
    /** @var array<int, bool> */
    private array $blockStart = [];
    /** @var array<int, bool> */
    private array $blockEnd = [];
    /** @var array<int, bool> every block opener (Tokens::detectBlockType), for containsOperation */
    private array $anyStart = [];
    /** @var array<int, bool> */
    private array $binary = [];
    /** @var array<int, bool> */
    private array $incDec = [];
    /** @var array<int, bool> */
    private array $predPrev = [];
    /** @var array<int, bool> */
    private array $casts = [];

    /** @param string[] $statements */
    public function __construct(private array $statements)
    {
        $T = '\__McTokId::';
        foreach ([CT::CLASS_CONSTANT, CT::DYNAMIC_VAR_BRACE_CLOSE, CT::RETURN_REF, CT::USE_LAMBDA, \__McTokId::T_ARRAY, \__McTokId::T_CATCH, \__McTokId::T_CLASS, \__McTokId::T_DECLARE,
            \__McTokId::T_ELSEIF, \__McTokId::T_EMPTY, \__McTokId::T_EXIT, \__McTokId::T_EVAL, \__McTokId::T_FN, \__McTokId::T_FOREACH, \__McTokId::T_FOR, \__McTokId::T_FUNCTION,
            \__McTokId::T_HALT_COMPILER, \__McTokId::T_IF, \__McTokId::T_ISSET, \__McTokId::T_LIST, \__McTokId::T_STRING, \__McTokId::T_SWITCH, \__McTokId::T_STATIC,
            \__McTokId::T_UNSET, \__McTokId::T_VARIABLE, \__McTokId::T_WHILE, \__McTokId::T_REQUIRE, \__McTokId::T_REQUIRE_ONCE, \__McTokId::T_INCLUDE,
            \__McTokId::T_INCLUDE_ONCE, \__McTokId::T_MATCH] as $k) {
            $this->negPre[$k] = true;
        }
        foreach ([59, 123, \__McTokId::T_OPEN_TAG, \__McTokId::T_OPEN_TAG_WITH_ECHO, \__McTokId::T_ECHO, \__McTokId::T_PRINT, \__McTokId::T_RETURN, \__McTokId::T_THROW,
            \__McTokId::T_YIELD, \__McTokId::T_YIELD_FROM, \__McTokId::T_BREAK, \__McTokId::T_CONTINUE, \__McTokId::T_REQUIRE, \__McTokId::T_REQUIRE_ONCE, \__McTokId::T_INCLUDE,
            \__McTokId::T_INCLUDE_ONCE] as $k) {
            $this->before[$k] = true;
        }
        foreach ([36, \__McTokId::T_CONSTANT_ENCAPSED_STRING, \__McTokId::T_DNUMBER, \__McTokId::T_DOUBLE_COLON, \__McTokId::T_LNUMBER, \__McTokId::T_NS_SEPARATOR,
            \__McTokId::T_STRING, \__McTokId::T_VARIABLE, \__McTokId::T_STATIC, \__McTokId::T_CLASS_C, \__McTokId::T_DIR, \__McTokId::T_FILE, \__McTokId::T_FUNC_C,
            \__McTokId::T_LINE, \__McTokId::T_METHOD_C, \__McTokId::T_NS_C, \__McTokId::T_TRAIT_C, \__McTokId::T_OBJECT_OPERATOR, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR] as $k) {
            $this->noop[$k] = true;
        }
        $this->anyStart = NsAnalysis::blockStartIds();
        foreach ([123, 40, 91, CT::ARRAY_BRACKET_OPEN, CT::DYNAMIC_PROP_BRACE_OPEN, CT::DYNAMIC_VAR_BRACE_OPEN, CT::ARRAY_INDEX_BRACE_OPEN, CT::DESTRUCTURING_BRACKET_OPEN] as $k) {
            $this->blockStart[$k] = true;
        }
        foreach ([125, 41, 93, CT::ARRAY_BRACKET_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE, CT::DYNAMIC_VAR_BRACE_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::DESTRUCTURING_BRACKET_CLOSE] as $k) {
            $this->blockEnd[$k] = true;
        }
        foreach ([\__McTokId::T_AND_EQUAL, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR, \__McTokId::T_CONCAT_EQUAL, \__McTokId::T_DIV_EQUAL, \__McTokId::T_DOUBLE_ARROW,
            \__McTokId::T_IS_EQUAL, \__McTokId::T_IS_GREATER_OR_EQUAL, \__McTokId::T_IS_IDENTICAL, \__McTokId::T_IS_NOT_EQUAL, \__McTokId::T_IS_NOT_IDENTICAL,
            \__McTokId::T_IS_SMALLER_OR_EQUAL, \__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR, \__McTokId::T_MINUS_EQUAL, \__McTokId::T_MOD_EQUAL,
            \__McTokId::T_MUL_EQUAL, \__McTokId::T_OR_EQUAL, \__McTokId::T_PLUS_EQUAL, \__McTokId::T_POW, \__McTokId::T_POW_EQUAL, \__McTokId::T_SL, \__McTokId::T_SL_EQUAL,
            \__McTokId::T_SR, \__McTokId::T_SR_EQUAL, \__McTokId::T_XOR_EQUAL, \__McTokId::T_SPACESHIP, \__McTokId::T_COALESCE, \__McTokId::T_COALESCE_EQUAL] as $k) {
            $this->binary[$k] = true;
        }
        foreach ([93, 125, 41, 34, 96, CT::ARRAY_BRACKET_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE, CT::DYNAMIC_VAR_BRACE_CLOSE, \__McTokId::T_CLASS_C,
            \__McTokId::T_CONSTANT_ENCAPSED_STRING, \__McTokId::T_DEC, \__McTokId::T_DIR, \__McTokId::T_DNUMBER, \__McTokId::T_FILE, \__McTokId::T_FUNC_C, \__McTokId::T_INC,
            \__McTokId::T_LINE, \__McTokId::T_LNUMBER, \__McTokId::T_METHOD_C, \__McTokId::T_NS_C, \__McTokId::T_STRING, \__McTokId::T_TRAIT_C, \__McTokId::T_VARIABLE] as $k) {
            $this->predPrev[$k] = true;
        }
        foreach ([\__McTokId::T_ARRAY_CAST, \__McTokId::T_BOOL_CAST, \__McTokId::T_DOUBLE_CAST, \__McTokId::T_INT_CAST, \__McTokId::T_OBJECT_CAST, \__McTokId::T_STRING_CAST,
            \__McTokId::T_UNSET_CAST, \__McTokId::T_VOID_CAST] as $k) {
            $this->casts[$k] = true;
        }
    }

    public function name(): string
    {
        return 'no_unneeded_control_parentheses';
    }

    public function priority(): int
    {
        return 30;
    }

    private bool $hasInstanceof = false;

    public function prepare(Source $s): bool
    {
        $paren = false;
        $this->hasInstanceof = false;
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === 40 || $s->ct[$i] === CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN) {
                $paren = true;
            } elseif ($k === \__McTokId::T_INSTANCEOF) {
                $this->hasInstanceof = true;
            }
        }
        return $paren;
    }

    // ---- token access: an effective id, and "gone" (cleared / replaced by a space) tokens are invisible ----

    private function id(int $i): int
    {
        return $this->s->ct[$i] !== 0 ? $this->s->ct[$i] : $this->s->kind[$i];
    }

    private function gone(int $i): bool
    {
        return isset($this->gone[$i]) || isset($this->rep[$i]);
    }

    private function prev(int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!$this->gone($j)) {
                return $j;
            }
        }
        return -1;
    }

    private function next(int $i): int
    {
        $n = $this->s->n;
        for ($j = $i + 1; $j < $n; $j++) {
            if (!$this->gone($j)) {
                return $j;
            }
        }
        return $n;
    }

    /** the token at $j is the plain char $c */
    private function ch(int $j, int $c): bool
    {
        return $j >= 0 && $j < $this->s->n && $this->s->ct[$j] === 0 && $this->s->kind[$j] === $c;
    }

    /** the token at $j has this effective id (a T_* kind or a custom id) */
    private function is(int $j, int $id): bool
    {
        return $j >= 0 && $j < $this->s->n && $this->id($j) === $id;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->gone = [];
        $this->rep = [];
        $n = $s->n;
        for ($open = 0; $open < $n; $open++) {
            if (!($s->kind[$open] === 40 && $s->ct[$open] === 0) && $s->ct[$open] !== CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN) {
                continue;
            }
            if ($this->gone($open)) {
                continue;
            }
            $close = $s->pair[$open];
            if ($close < 0) {
                continue;
            }
            $quiet = $this->gone === [] && $this->rep === [];
            $bo = $quiet ? $open - 1 : $this->prev($open);
            if ($bo < 0) {
                continue;
            }
            $bid = $s->ct[$bo] !== 0 ? $s->ct[$bo] : $s->kind[$bo];
            if (isset($this->negPre[$bid]) && $bid !== \__McTokId::T_EXIT && ($quiet ? $open + 1 !== $close : $this->next($open) !== $close)) {
                continue; // `foo(1, 2)`, `if (...)`, `function (...)`: the cheap negative case first
            }
            $ac = $quiet ? $close + 1 : $this->next($close);
            if ($ac >= $n) {
                continue;
            }
            if ($this->next($open) === $close) {
                if ($bid === \__McTokId::T_EXIT) {
                    $this->remove($bo, $ac, $open, $close, 'others');
                }
                continue;
            }
            if (isset($this->negPre[$bid])) {
                continue;
            }
            if ($this->isUselessWrapped($bo, $ac)) {
                $this->remove($bo, $ac, $open, $close, $this->configType($bo));
                continue;
            }
            if ($this->is($bo, \__McTokId::T_CLONE)) {
                if ($this->isWrappedCloneArgument($bo, $open, $close, $ac)) {
                    $this->remove($bo, $ac, $open, $close, 'clone');
                }
                continue;
            }
            $inst = $this->hasInstanceof ? $this->instanceOfIndex($open, $close) : -1;
            if ($inst >= 0) {
                if ($this->isWrappedInstanceOf($inst, $bo, $open, $close, $ac)) {
                    $this->remove($bo, $ac, $open, $close, $this->ch($bo, 33) ? 'negative_instanceof' : 'others');
                }
                continue;
            }
            if ($this->isWrappedPartOfOperation($bo, $open, $close, $ac)) {
                $this->remove($bo, $ac, $open, $close, $this->configType($bo));
            }
        }
        foreach ($this->gone as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, '');
        }
        foreach ($this->rep as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, ' ');
        }
        $this->gone = [];
        $this->rep = [];
    }

    private function configType(int $bo): ?string
    {
        $k = $this->id($bo);
        if ($k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE) {
            return null;
        }
        switch ($k) {
            case \__McTokId::T_BREAK:
                return 'break';
            case \__McTokId::T_CASE:
                return 'switch_case';
            case \__McTokId::T_CONTINUE:
                return 'continue';
            case \__McTokId::T_ECHO:
            case \__McTokId::T_PRINT:
                return 'echo_print';
            case \__McTokId::T_RETURN:
                return 'return';
            case \__McTokId::T_YIELD:
                return 'yield';
            case \__McTokId::T_YIELD_FROM:
                return 'yield_from';
        }
        return 'others';
    }

    private function isUselessWrapped(int $bo, int $ac): bool
    {
        return $this->isSingleStatement($bo, $ac) || $this->isWrappedFnBody($bo, $ac) || $this->isWrappedForElement($bo, $ac)
            || $this->isWrappedLanguageConstructArgument($bo, $ac) || $this->isWrappedSequenceElement($bo, $ac);
    }

    private function isWrappedCloneArgument(int $bo, int $open, int $close, int $ac): bool
    {
        $bo = $this->prev($bo);
        if ($bo < 0) {
            return false;
        }
        if (!($this->ch($bo, 63) || $this->isSimpleAssignment($bo, $ac) || $this->isSingleStatement($bo, $ac) || $this->isWrappedFnBody($bo, $ac)
            || $this->isWrappedForElement($bo, $ac) || $this->isWrappedSequenceElement($bo, $ac))) {
            return false;
        }
        $nc = $this->next($open);
        if ($this->is($nc, \__McTokId::T_NEW)) {
            $open = $nc;
        }
        return !$this->containsOperation($open, $close);
    }

    private function instanceOfIndex(int $open, int $close): int
    {
        $found = -1;
        $cnt = 0;
        for ($j = $open + 1; $j < $close; $j++) {
            if (!$this->gone($j) && $this->s->kind[$j] === \__McTokId::T_INSTANCEOF && $this->s->ct[$j] === 0) {
                $cnt++;
                if ($found < 0) {
                    $found = $j;
                }
            }
        }
        return $cnt === 1 ? $found : -1;
    }

    private function isWrappedInstanceOf(int $inst, int $bo, int $open, int $close, int $ac): bool
    {
        if ($this->containsOperation($open, $inst) || $this->containsOperation($inst, $close)) {
            return false;
        }
        if ($this->ch($bo, 33)) {
            $bo = $this->prev($bo);
            if ($bo < 0) {
                return false;
            }
        }
        return $this->isSimpleAssignment($bo, $ac) || $this->isSingleStatement($bo, $ac) || $this->isWrappedFnBody($bo, $ac) || $this->isWrappedForElement($bo, $ac)
            || $this->isWrappedSequenceElement($bo, $ac);
    }

    private function isWrappedPartOfOperation(int $bo, int $open, int $close, int $ac): bool
    {
        if ($this->containsOperation($open, $close)) {
            return false;
        }
        $moved = false;
        if ($this->isPreUnaryOperation($bo)) {
            $bo = $this->beforePreUnary($bo);
            $moved = true;
        }
        if ($this->isAccess($ac)) {
            $ac = $this->afterAccess($ac);
            $moved = true;
            if ($ac < $this->s->n && $this->isUnarySuccessor($ac)) {
                $ac = $this->next($ac);
            }
        }
        if ($bo < 0 || $ac >= $this->s->n) {
            return false;
        }
        if ($moved) {
            if (isset($this->negPre[$this->id($bo)])) {
                return false;
            }
            if ($this->isUselessWrapped($bo, $ac)) {
                return true;
            }
        }
        $beforeBinary = $this->isBinaryOperator($bo);
        $afterBinary = $this->isBinaryOperator($ac);
        if ($beforeBinary && $afterBinary) {
            return true;
        }
        $beforeOpenOrComma = $this->ch($bo, 44) || $this->blockStartAt($bo);
        $afterEndOrComma = $this->ch($ac, 44) || $this->blockEndAt($ac);
        if (($beforeOpenOrComma && $afterBinary) || ($beforeBinary && $afterEndOrComma)) {
            return true;
        }
        if ($this->ch($bo, 125)) {
            $beforeStmtOpen = !$this->closeCurlyBelongsToDynamicElement($bo);
        } else {
            $beforeStmtOpen = isset($this->before[$this->id($bo)]) || $this->is($bo, \__McTokId::T_CASE);
        }
        $afterStmtEnd = $this->ch($ac, 59) || $this->is($ac, \__McTokId::T_CLOSE_TAG);
        return ($beforeStmtOpen && $afterBinary) || ($beforeBinary && $afterStmtEnd);
    }

    private function isWrappedLanguageConstructArgument(int $bo, int $ac): bool
    {
        $k = $this->id($bo);
        if (!($k === \__McTokId::T_PRINT || $k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE
            || $k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE)) {
            return false;
        }
        $bo = $this->prev($bo);
        return $bo >= 0 && $this->isWrappedSequenceElement($bo, $ac);
    }

    private function isSingleStatement(int $bo, int $ac): bool
    {
        if ($this->is($bo, \__McTokId::T_CASE)) {
            return $this->ch($ac, 58) || $this->ch($ac, 59);
        }
        if (!($this->ch($ac, 59) || $this->is($ac, \__McTokId::T_CLOSE_TAG))) {
            return false;
        }
        if ($this->ch($bo, 125)) {
            return !$this->closeCurlyBelongsToDynamicElement($bo);
        }
        return isset($this->before[$this->id($bo)]);
    }

    private function isSimpleAssignment(int $bo, int $ac): bool
    {
        return $this->ch($bo, 61) && ($this->ch($ac, 59) || $this->is($ac, \__McTokId::T_CLOSE_TAG));
    }

    private function isWrappedSequenceElement(int $start, int $end): bool
    {
        $sc = $this->ch($start, 44);
        $ec = $this->ch($end, 44);
        if ($sc && $ec) {
            return true;
        }
        $bs = $this->blockStartAt($start);
        $be = $this->blockEndAt($end);
        return ($sc && $be) || ($ec && $bs) || ($be && $bs);
    }

    private function isWrappedForElement(int $bo, int $ac): bool
    {
        $f = -1;
        if ($this->ch($bo, 40) && $this->ch($ac, 59)) {
            $f = $this->prev($bo);
        } elseif ($this->ch($ac, 41) && $this->ch($bo, 59)) {
            $f = $this->prev($this->s->pair[$ac]);
        }
        return $f >= 0 && $this->is($f, \__McTokId::T_FOR);
    }

    private function isWrappedFnBody(int $bo, int $ac): bool
    {
        if (!$this->is($bo, \__McTokId::T_DOUBLE_ARROW)) {
            return false;
        }
        $bo = $this->prev($bo);
        if ($bo < 0) {
            return false;
        }
        if ($this->is($bo, \__McTokId::T_STRING)) {
            while (true) {
                $bo = $this->prev($bo);
                if ($bo < 0 || !($this->is($bo, \__McTokId::T_STRING) || $this->is($bo, CT::TYPE_INTERSECTION) || $this->is($bo, CT::TYPE_ALTERNATION))) {
                    break;
                }
            }
            if ($bo < 0 || !$this->is($bo, CT::TYPE_COLON)) {
                return false;
            }
            $bo = $this->prev($bo);
        }
        if ($bo < 0 || !$this->ch($bo, 41)) {
            return false;
        }
        $bo = $this->prev($this->s->pair[$bo]);
        if ($bo >= 0 && $this->is($bo, CT::RETURN_REF)) {
            $bo = $this->prev($bo);
        }
        if ($bo < 0 || !$this->is($bo, \__McTokId::T_FN)) {
            return false;
        }
        return $this->ch($ac, 59) || $this->ch($ac, 44) || $this->is($ac, \__McTokId::T_CLOSE_TAG);
    }

    // ---- TokensAnalyzer ----

    private function isUnarySuccessor(int $i): bool
    {
        if (!$this->is($i, \__McTokId::T_INC) && !$this->is($i, \__McTokId::T_DEC)) {
            return false;
        }
        $p = $this->prev($i);
        if ($p < 0) {
            return false;
        }
        $k = $this->id($p);
        return $k === 93 || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE || $k === CT::ARRAY_INDEX_BRACE_CLOSE || $k === CT::DYNAMIC_PROP_BRACE_CLOSE
            || $k === CT::DYNAMIC_VAR_BRACE_CLOSE;
    }

    private function isUnaryPredecessor(int $i): bool
    {
        $s = $this->s;
        $k = $this->id($i);
        if ($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) {
            return !$this->isUnarySuccessor($i);
        }
        if ($k === 33 || $k === 126 || $k === 64 || $k === \__McTokId::T_ELLIPSIS) {
            return true;
        }
        $amp = $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
        if (!($k === 43 || $k === 45 || $amp || $k === CT::RETURN_REF)) {
            return false;
        }
        $p = $this->prev($i);
        if ($p < 0) {
            return true;
        }
        if (!isset($this->predPrev[$this->id($p)])) {
            return true;
        }
        if (!$amp || $this->id($p) !== \__McTokId::T_STRING) {
            return false;
        }
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($this->gone($j)) {
                continue;
            }
            $x = $this->id($j);
            if ($x === 59 || $x === 123 || $x === 125 || $x === \__McTokId::T_DOUBLE_ARROW || $x === \__McTokId::T_FN || $x === \__McTokId::T_FUNCTION
                || $x === \__McTokId::T_OPEN_TAG || $x === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return $x === \__McTokId::T_FN || $x === \__McTokId::T_FUNCTION;
            }
        }
        return false;
    }

    private function isBinaryOperator(int $i): bool
    {
        $s = $this->s;
        if ($i < 0 || $i >= $s->n) {
            return false;
        }
        $k = $this->id($i);
        if ($k === \__McTokId::T_INLINE_HTML || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === CT::TYPE_INTERSECTION) {
            return false;
        }
        if ($s->end[$i] - $s->start[$i] === 1) {
            $ch = $s->code[$s->start[$i]];
            if ($ch === '+' || $ch === '-' || $ch === '&') {
                return !$this->isUnaryPredecessor($i);
            }
        }
        if ($s->ct[$i] !== 0 || $s->kind[$i] >= 256) {
            return isset($this->binary[$k]);
        }
        return $k === 61 || $k === 42 || $k === 47 || $k === 37 || $k === 60 || $k === 62 || $k === 124 || $k === 94 || $k === 46;
    }

    private function isPreUnaryOperation(int $i): bool
    {
        return $i >= 0 && ($this->isUnaryPredecessor($i) || isset($this->casts[$this->id($i)]));
    }

    private function beforePreUnary(int $i): int
    {
        do {
            $i = $this->prev($i);
        } while ($i >= 0 && $this->isPreUnaryOperation($i));
        return $i;
    }

    private function isAccess(int $i): bool
    {
        if ($i < 0 || $i >= $this->s->n) {
            return false;
        }
        $k = $this->id($i);
        return $k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $k === 91 || $k === CT::ARRAY_INDEX_BRACE_OPEN;
    }

    private function afterAccess(int $i): int
    {
        while ($i < $this->s->n) {
            if ($this->blockStartAt($i)) {
                $i = $this->s->pair[$i];
                $i = $this->next($i);
                continue;
            }
            $k = $this->id($i);
            if ($k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $k === 36 || $k === \__McTokId::T_DOUBLE_COLON
                || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE) {
                $i = $this->next($i);
                continue;
            }
            break;
        }
        return $i;
    }

    private function blockStartAt(int $i): bool
    {
        return $i >= 0 && $i < $this->s->n && isset($this->blockStart[$this->id($i)]);
    }

    private function blockEndAt(int $i): bool
    {
        return $i >= 0 && $i < $this->s->n && isset($this->blockEnd[$this->id($i)]);
    }

    private function containsOperation(int $a, int $b): bool
    {
        $i = $a;
        while (true) {
            $i = $this->next($i);
            if ($i === $b || $i >= $this->s->n) {
                break;
            }
            if (isset($this->anyStart[$this->id($i)])) {
                $i = $this->s->pair[$i];
                continue;
            }
            if (!isset($this->noop[$this->id($i)])) {
                return true;
            }
        }
        return false;
    }

    private function closeCurlyBelongsToDynamicElement(int $bo): bool
    {
        $s = $this->s;
        $o = $s->pair[$bo];
        $i = $o >= 0 ? $this->prev($o) : -1;
        if ($i < 0) {
            return false;
        }
        if ($this->is($i, \__McTokId::T_DOUBLE_COLON)) {
            return true;
        }
        if ($this->ch($i, 58)) {
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($this->gone($j)) {
                    continue;
                }
                if ($this->is($j, \__McTokId::T_CASE) || $this->ch($j, 63)) {
                    return !$this->is($j, \__McTokId::T_CASE);
                }
            }
            return true;
        }
        return false;
    }

    // ---- removal ----

    private function remove(int $bo, int $ac, int $open, int $close, ?string $type): void
    {
        if ($type === null || !\in_array($type, $this->statements, true)) {
            return;
        }
        $needsAfter = !$this->isAccess($ac) && !($this->ch($ac, 59) || $this->ch($ac, 44) || $this->is($ac, \__McTokId::T_CLOSE_TAG)) && !$this->blockEndAt($ac)
            && !(($this->ch($ac, 58) || $this->ch($ac, 59)) && $this->is($bo, \__McTokId::T_CASE));
        $needsBefore = !$this->isPreUnaryOperation($bo) && !($this->ch($bo, 125) || $this->is($bo, \__McTokId::T_EXIT) || $this->is($bo, \__McTokId::T_OPEN_TAG))
            && !$this->blockStartAt($bo);
        $this->removeBrace($close, $needsAfter);
        $this->removeBrace($open, $needsBefore);
    }

    /** the whitespace-or-comment test of a neighbour: the merged gap on that side (gone tokens contribute their gaps, replaced ones a space) */
    private function sideHasTrivia(int $i, int $dir): bool
    {
        $s = $this->s;
        if ($dir < 0) {
            if ($s->gapBefore[$i] !== '') {
                return true;
            }
            for ($j = $i - 1; $j >= 0 && $this->gone($j); $j--) {
                if (isset($this->rep[$j]) || $s->gapBefore[$j] !== '') {
                    return true;
                }
            }
            return false;
        }
        $n = $s->n;
        if ($s->gapBefore[$i + 1] !== '') {
            return true;
        }
        for ($j = $i + 1; $j < $n && $this->gone($j); $j++) {
            if (isset($this->rep[$j]) || $s->gapBefore[$j + 1] !== '') {
                return true;
            }
        }
        return false;
    }

    private function removeBrace(int $i, bool $needsSpace): void
    {
        if ($needsSpace && ($this->sideHasTrivia($i, -1) || $this->sideHasTrivia($i, 1))) {
            $needsSpace = false;
        }
        if ($needsSpace) {
            $this->rep[$i] = true;
        } else {
            $this->gone[$i] = true;
        }
    }
}
