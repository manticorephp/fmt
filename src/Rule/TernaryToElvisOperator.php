<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\TernaryToElvisOperatorFixer (priority 2, risky). `$a ? $a : 1` -> `$a ?: 1` when the operand before `?` equals the
 * one after it (RangeAnalyzer::rangeEqualsRange); the repeated operand is cleared. Whole-file FlatTokens port, last `?` first.
 */
final class TernaryToElvisOperator extends FlatRule
{
    public function name(): string
    {
        return 'ternary_to_elvis_operator';
    }

    public function priority(): int
    {
        return 2;
    }

    /** the exact test on the untouched Source (no edit happens in a file where no `?` has equal operands, so there the flat pass has nothing to do either) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 2; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== 63 || $s->ct[$i] !== 0 || $s->kind[$i + 1] === 58) {
                continue;
            }
            $before = $this->srcBefore($s, $i);
            if ($before === null) {
                continue;
            }
            $after = $this->srcAfter($s, $i);
            if ($after !== null && $this->srcRangeEq($s, $before[0], $before[1], $after[0], $after[1])) {
                return true;
            }
        }
        return false;
    }

    private static function ek(Source $s, int $j): int
    {
        return $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
    }

    private static function validBeforeS(Source $s, int $j): bool
    {
        $k = self::ek($s, $j);
        return $k === 61 || $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === 40 || $k === 44 || $k === 59 || $k === 91 || $k === 123
            || $k === 125 || $k === CT::ARRAY_INDEX_BRACE_OPEN || $k === \__McTokId::T_AND_EQUAL || $k === \__McTokId::T_CONCAT_EQUAL || $k === \__McTokId::T_DIV_EQUAL
            || $k === \__McTokId::T_MINUS_EQUAL || $k === \__McTokId::T_MOD_EQUAL || $k === \__McTokId::T_MUL_EQUAL || $k === \__McTokId::T_OR_EQUAL
            || $k === \__McTokId::T_PLUS_EQUAL || $k === \__McTokId::T_POW_EQUAL || $k === \__McTokId::T_SL_EQUAL || $k === \__McTokId::T_SR_EQUAL
            || $k === \__McTokId::T_XOR_EQUAL;
    }

    /** @return int[]|null */
    private function srcBefore(Source $s, int $q): ?array
    {
        $index = $q - 1;
        $end = $index;
        $start = -1;
        while ($index >= 0 && !self::validBeforeS($s, $index)) {
            $k = self::ek($s, $index);
            if ($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) {
                return null;
            }
            $det = self::blockEdge($k);
            if ($det === null || $det[1] === 1) {
                $start = $index;
                $index--;
                continue;
            }
            $open = $det[0];
            $close = \Fmt\TypeWalk::closer($open);
            $openCount = 1;
            do {
                $index--;
                if ($index < 0) {
                    return null;
                }
                $kk = self::ek($s, $index);
                if ($kk === \__McTokId::T_INC || $kk === \__McTokId::T_DEC) {
                    return null;
                }
                if ($kk === $open) {
                    $openCount++;
                    continue;
                }
                if ($kk === $close) {
                    $openCount--;
                }
            } while ($openCount <= 1);
            $start = $index;
            $index--;
        }
        if ($start < 0 || $index < 0) {
            return null;
        }
        return [$start, $end];
    }

    /** @return int[]|null */
    private function srcAfter(Source $s, int $q): ?array
    {
        $n = $s->n;
        $index = $q + 1;
        $start = $index;
        $end = $index;
        do {
            if ($index >= $n) {
                return null;
            }
            $det = self::blockEdge(self::ek($s, $index));
            if ($det !== null) {
                if ($det[1] === 0 || $s->pair[$index] < 0) {
                    return null;
                }
                $index = $s->pair[$index];
            }
            $end = $index;
            $index++;
        } while ($index < $n && !($s->kind[$index] === 58 && $s->ct[$index] === 0));
        return $index < $n ? [$start, $end] : null;
    }

    private function srcRangeEq(Source $s, int $ls, int $le, int $rs, int $re): bool
    {
        while (self::ek($s, $ls) === 40 && self::ek($s, $le) === 41) {
            $ls++;
            $le--;
        }
        while (self::ek($s, $rs) === 40 && self::ek($s, $re) === 41) {
            $rs++;
            $re--;
        }
        while (true) {
            $lk = self::ek($s, $ls);
            $rk = self::ek($s, $rs);
            $lo = $lk === 91 || $lk === CT::ARRAY_INDEX_BRACE_OPEN;
            $ro = $rk === 91 || $rk === CT::ARRAY_INDEX_BRACE_OPEN;
            $lc = $lk === 93 || $lk === CT::ARRAY_INDEX_BRACE_CLOSE;
            $rc = $rk === 93 || $rk === CT::ARRAY_INDEX_BRACE_CLOSE;
            if (!($lk === $rk && $s->text($ls) === $s->text($rs)) && !($lo && $ro) && !($lc && $rc)) {
                return false;
            }
            $ls++;
            $rs++;
            $reachedL = $ls >= $s->n || $ls > $le;
            $reachedR = $rs >= $s->n || $rs > $re;
            if (!$reachedL && !$reachedR) {
                continue;
            }
            return $reachedL && $reachedR;
        }
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 5; $q > 1; $q--) {
            if (!self::is($t, $q, 63)) {
                continue;
            }
            $next = $t->meaningful($q, 1);
            if (self::is($t, $next, 58)) {
                continue;
            }
            $before = $this->before($t, $q);
            if ($before === null) {
                continue;
            }
            $after = $this->after($t, $q);
            if (self::rangeEq($t, $before[0], $before[1], $after[0], $after[1])) {
                for ($i = $after[1]; $i >= $after[0]; $i = $t->meaningful($i, -1)) {
                    self::clearMerge($t, $i);
                }
            }
        }
    }

    private static function validBefore(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === 61 || $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === 40 || $k === 44 || $k === 59 || $k === 91 || $k === 123
            || $k === 125 || $k === CT::ARRAY_INDEX_BRACE_OPEN || $k === \__McTokId::T_AND_EQUAL || $k === \__McTokId::T_CONCAT_EQUAL || $k === \__McTokId::T_DIV_EQUAL
            || $k === \__McTokId::T_MINUS_EQUAL || $k === \__McTokId::T_MOD_EQUAL || $k === \__McTokId::T_MUL_EQUAL || $k === \__McTokId::T_OR_EQUAL
            || $k === \__McTokId::T_PLUS_EQUAL || $k === \__McTokId::T_POW_EQUAL || $k === \__McTokId::T_SL_EQUAL || $k === \__McTokId::T_SR_EQUAL
            || $k === \__McTokId::T_XOR_EQUAL;
    }

    /** @return int[]|null [start, end] */
    private function before(FlatTokens $t, int $q): ?array
    {
        $index = $t->meaningful($q, -1);
        $end = $index;
        $start = -1;
        while ($index >= 0 && !self::validBefore($t, $index)) {
            $k = $t->k[$index];
            if ($k === \__McTokId::T_INC || $k === \__McTokId::T_DEC) {
                return null;
            }
            $det = self::blockEdge($k);
            if ($det === null || $det[1] === 1) {
                $start = $index;
                $index = $t->meaningful($index, -1);
                continue;
            }
            $open = $det[0];
            $openCount = 1;
            do {
                $index = $t->meaningful($index, -1);
                if ($index < 0) {
                    return null;
                }
                $kk = $t->k[$index];
                if ($kk === \__McTokId::T_INC || $kk === \__McTokId::T_DEC) {
                    return null;
                }
                if ($kk === $open) {
                    $openCount++;
                    continue;
                }
                if ($kk === \Fmt\TypeWalk::closer($open)) {
                    $openCount--;
                }
            } while ($openCount <= 1);
            $start = $index;
            $index = $t->meaningful($index, -1);
        }
        if ($start < 0 || $index < 0) {
            return null;
        }
        return [$start, $end];
    }

    /** @return int[] [start, end] */
    private function after(FlatTokens $t, int $q): array
    {
        $index = $t->meaningful($q, 1);
        $start = $index;
        $end = $index;
        do {
            $det = self::blockEdge($t->k[$index]);
            if ($det !== null) {
                $index = $t->blockEnd($index, \Fmt\TypeWalk::closer($det[0]));
            }
            $end = $index;
            $index = $t->meaningful($index, 1);
        } while ($index >= 0 && !self::is($t, $index, 58));
        return [$start, $end];
    }

    private static function rangeEq(FlatTokens $t, int $ls, int $le, int $rs, int $re): bool
    {
        if ($t->k[$ls] === FlatTokens::WS || $t->k[$ls] === FlatTokens::COMMENT) {
            $ls = $t->meaningful($ls, 1);
        }
        while (self::is($t, $ls, 40) && self::is($t, $le, 41)) {
            $ls = $t->meaningful($ls, 1);
            $le = $t->meaningful($le, -1);
        }
        if ($t->k[$rs] === FlatTokens::WS || $t->k[$rs] === FlatTokens::COMMENT) {
            $rs = $t->meaningful($rs, 1);
        }
        while (self::is($t, $rs, 40) && self::is($t, $re, 41)) {
            $rs = $t->meaningful($rs, 1);
            $re = $t->meaningful($re, -1);
        }
        while (true) {
            $lk = $t->k[$ls];
            $rk = $t->k[$rs];
            $lo = $lk === 91 || $lk === CT::ARRAY_INDEX_BRACE_OPEN;
            $ro = $rk === 91 || $rk === CT::ARRAY_INDEX_BRACE_OPEN;
            $lc = $lk === 93 || $lk === CT::ARRAY_INDEX_BRACE_CLOSE;
            $rc = $rk === 93 || $rk === CT::ARRAY_INDEX_BRACE_CLOSE;
            if (!self::tokEq($t, $ls, $rs) && !($lo && $ro) && !($lc && $rc)) {
                return false;
            }
            $ls = $t->meaningful($ls, 1);
            $rs = $t->meaningful($rs, 1);
            $reachedL = $ls < 0 || $ls > $le;
            $reachedR = $rs < 0 || $rs > $re;
            if (!$reachedL && !$reachedR) {
                continue;
            }
            return $reachedL && $reachedR;
        }
    }
}
