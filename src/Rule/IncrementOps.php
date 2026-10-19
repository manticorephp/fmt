<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatBlocks;
use Fmt\FlatTokens;

/** AbstractIncrementOperatorFixer::findStart and IncrementStyleFixer::findEnd, TokensAnalyzer::isUnarySuccessorOperator, on a FlatTokens. */
final class IncrementOps
{
    private FlatBlocks $b;

    public function __construct()
    {
        $this->b = new FlatBlocks();
    }

    private static function is(FlatTokens $t, int $q, int $id): bool
    {
        return $q >= 0 && $q < $t->count() && $t->i[$q] !== -1 && $t->k[$q] === $id;
    }

    public function findStart(FlatTokens $t, int $q): int
    {
        do {
            $q = $t->meaningful($q, -1);
            if ($q < 0) {
                return -1;
            }
            if ($this->b->isEnd($t, $q)) {
                $q = $this->b->start($t, $q);
                if ($q < 0) {
                    return -1;
                }
            }
        } while (!($t->k[$q] === 36 || $t->k[$q] === \__McTokId::T_VARIABLE));
        $p = $t->meaningful($q, -1);
        if (self::is($t, $p, 36)) {
            return $this->findStart($t, $q);
        }
        if (self::is($t, $p, \__McTokId::T_OBJECT_OPERATOR) || self::is($t, $p, \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
            return $this->findStart($t, $p);
        }
        if (self::is($t, $p, \__McTokId::T_DOUBLE_COLON)) {
            $pp = $t->meaningful($p, -1);
            if (!(self::is($t, $pp, \__McTokId::T_STATIC) || self::is($t, $pp, \__McTokId::T_STRING))) {
                return $this->findStart($t, $p);
            }
            $j = $p;
            while (true) {
                $j--;
                if ($j < 0) {
                    return $q;
                }
                if ($t->isEmpty($j)) {
                    continue;
                }
                if ($t->i[$j] !== -1 && ($t->k[$j] === \__McTokId::T_NS_SEPARATOR || $t->k[$j] === \__McTokId::T_STATIC || $t->k[$j] === \__McTokId::T_STRING)) {
                    continue;
                }
                break;
            }
            $q = $t->meaningful($j, 1);
        }
        return $q;
    }

    public function findEnd(FlatTokens $t, int $q): int
    {
        $n = $t->meaningful($q, 1);
        while ($n >= 0 && $t->i[$n] !== -1 && $this->continues($t->k[$n])) {
            if ($this->b->isStart($t, $n)) {
                $n = $this->b->end($t, $n);
                if ($n < 0) {
                    return $q;
                }
            }
            $q = $n;
            $n = $t->meaningful($n, 1);
        }
        if ($n >= 0 && ($t->k[$n] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$n] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) && $t->i[$n] !== -1) {
            return $this->findEnd($t, $n);
        }
        if (self::is($t, $n, \__McTokId::T_DOUBLE_COLON)) {
            return $this->findEnd($t, $t->meaningful($n, 1));
        }
        return $q;
    }

    private function continues(int $k): bool
    {
        return $k === 36 || $k === 40 || $k === 91 || $k === CT::DYNAMIC_PROP_BRACE_OPEN || $k === CT::DYNAMIC_VAR_BRACE_OPEN || $k === CT::ARRAY_INDEX_BRACE_OPEN
            || $k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE;
    }

    public function isSuccessor(FlatTokens $t, int $q): bool
    {
        $p = $t->meaningful($q, -1);
        if ($p < 0 || $t->i[$p] === -1) {
            return false;
        }
        $k = $t->k[$p];
        return $k === 93 || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE || $k === CT::ARRAY_INDEX_BRACE_CLOSE
            || $k === CT::DYNAMIC_PROP_BRACE_CLOSE || $k === CT::DYNAMIC_VAR_BRACE_CLOSE;
    }
}
