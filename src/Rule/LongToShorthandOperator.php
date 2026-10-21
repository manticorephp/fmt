<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatAnalyzer;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\LongToShorthandOperatorFixer + AbstractShortOperatorFixer (priority 17, risky). `$a = $a + 1;` -> `$a += 1;` (also - * / & . % | ^;
 * `$a = 2 * $a` for the commutative ones). Whole-file FlatTokens port, last operator first.
 */
final class LongToShorthandOperator extends FlatRule
{
    private FlatAnalyzer $an;

    public function __construct()
    {
        parent::__construct();
        $this->an = new FlatAnalyzer();
    }

    public function name(): string
    {
        return 'long_to_shorthand_operator';
    }

    public function priority(): int
    {
        return 17;
    }

    private static function opKind(int $k): bool
    {
        return $k === 43 || $k === 45 || $k === 42 || $k === 47 || $k === 38 || $k === 46 || $k === 37 || $k === 124 || $k === 94
            || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    /** a statement `A = .. op ..` whose last token before `=` is also the last token before an operator or before the statement end (or a `)`): a superset of the rewrites */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 1; $i + 2 < $n; $i++) {
            if ($s->kind[$i] !== 61 || $s->ct[$i] !== 0) {
                continue;
            }
            $lastLen = $s->end[$i - 1] - $s->start[$i - 1];
            $lastKind = $s->kind[$i - 1];
            $e = -1;
            $ops = [];
            for ($j = $i + 1; $j < $n; $j++) {
                $k = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if ($k === 59 || $k === 44 || $k === \__McTokId::T_CLOSE_TAG) {
                    $e = $j;
                    break;
                }
                $det = self::blockEdge($k);
                if ($det !== null) {
                    if ($det[1] === 1 && $s->pair[$j] > $j) {
                        $j = $s->pair[$j];
                        continue;
                    }
                    $e = $j;
                    break;
                }
                if (self::opKind($k)) {
                    $ops[] = $j;
                }
            }
            if ($e < 0 || $ops === []) {
                continue;
            }
            if (self::sameTok($s, $i - 1, $lastKind, $lastLen, $e - 1)) {
                return true;
            }
            foreach ($ops as $o) {
                if (self::sameTok($s, $i - 1, $lastKind, $lastLen, $o - 1)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function sameTok(Source $s, int $a, int $ak, int $al, int $b): bool
    {
        if ($b <= $a) {
            return false;
        }
        $bk = $s->ct[$b] !== 0 ? $s->ct[$b] : $s->kind[$b];
        if ($bk === 41) {
            return true;
        }
        return $s->kind[$b] === $ak && $s->end[$b] - $s->start[$b] === $al && $s->text($a) === $s->text($b);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index > 3; $index--) {
            if (!$this->isOperatorCandidate($t, $index)) {
                continue;
            }
            $before = $this->beforeRange($t, $index);
            $eq = $t->meaningful($before[0], -1);
            if (!self::is($t, $eq, 61)) {
                continue;
            }
            $assign = $this->beforeRange($t, $eq);
            $beforeAssign = $t->meaningful($assign[0], -1);
            if (self::is($t, $beforeAssign, 58)) {
                if (!$this->belongsToSwitchOrAlt($t, $beforeAssign)) {
                    continue;
                }
            } elseif (!(self::is($t, $beforeAssign, 59) || self::is($t, $beforeAssign, 123) || self::is($t, $beforeAssign, 125) || self::is($t, $beforeAssign, 40)
                || self::is($t, $beforeAssign, 41) || self::is($t, $beforeAssign, 44) || self::is($t, $beforeAssign, \__McTokId::T_OPEN_TAG)
                || self::is($t, $beforeAssign, \__McTokId::T_RETURN))) {
                continue;
            }
            if (self::rangeEquals($t, $assign[0], $assign[1], $before[0], $before[1])) {
                $this->shorten($t, $eq, $index, $assign, $before);
                continue;
            }
            if (!$this->commutative($t, $index)) {
                continue;
            }
            $after = $this->afterRange($t, $index);
            if (!self::rangeEquals($t, $assign[0], $assign[1], $after[0], $after[1])) {
                continue;
            }
            $this->shorten($t, $eq, $index, $assign, $after);
        }
    }

    private function isOperatorCandidate(FlatTokens $t, int $index): bool
    {
        if ($t->i[$index] === -1 || !self::opKind($t->k[$index])) {
            return false;
        }
        while ($index >= 0) {
            $index = $t->meaningful($index, 1);
            if ($index < 0) {
                return false;
            }
            $k = $t->k[$index];
            if ($k === 59 || $k === \__McTokId::T_CLOSE_TAG) {
                return true;
            }
            if ($k === 63 || $k === \__McTokId::T_INSTANCEOF) {
                return false;
            }
            $det = self::blockEdge($k);
            if ($det !== null) {
                if ($det[1] === 0) {
                    return true;
                }
                $index = $t->blockEnd($index, TypeWalk::closer($det[0]));
                continue;
            }
            if ($this->an->isBinaryOperator($t, $index)) {
                return false;
            }
        }
        return false;
    }

    private function replacement(FlatTokens $t, int $q): array
    {
        $c = $t->content($q);
        return match ($c) {
            '+' => [\__McTokId::T_PLUS_EQUAL, '+='], '-' => [\__McTokId::T_MINUS_EQUAL, '-='], '*' => [\__McTokId::T_MUL_EQUAL, '*='], '/' => [\__McTokId::T_DIV_EQUAL, '/='],
            '&' => [\__McTokId::T_AND_EQUAL, '&='], '.' => [\__McTokId::T_CONCAT_EQUAL, '.='], '%' => [\__McTokId::T_MOD_EQUAL, '%='], '|' => [\__McTokId::T_OR_EQUAL, '|='],
            default => [\__McTokId::T_XOR_EQUAL, '^='],
        };
    }

    private function commutative(FlatTokens $t, int $q): bool
    {
        $c = $t->content($q);
        return $c === '*' || $c === '|' || $c === '&' || $c === '^';
    }

    /** @param int[] $assign @param int[] $range */
    private function shorten(FlatTokens $t, int $eq, int $op, array $assign, array $range): void
    {
        $r = $this->replacement($t, $op);
        self::setCode($t, $eq, $r[0], $r[1]);
        self::clearMerge($t, $op);
        for ($i = $range[1]; $i >= $range[0]; $i = $t->meaningful($i, -1)) {
            self::clearMerge($t, $i);
        }
        foreach ([$eq, $assign[1]] as $x) {
            $i = $t->nonEmptySibling($x, 1);
            if ($i < 0) {
                continue;
            }
            if (self::wsOnly($t, $i, " \t")) {
                $t->c[$i] = ' ';
            } elseif (!self::wsOnly($t, $i, " \t\n\r\0\x0B")) {
                $t->insertAt($i, [FlatTokens::ws(' ')]);
            }
        }
    }

    /** @return int[] */
    private function afterRange(FlatTokens $t, int $index): array
    {
        $index = $t->meaningful($index, 1);
        $start = $index;
        while (true) {
            $next = $t->meaningful($index, 1);
            if ($next < 0 || self::is($t, $next, 59) || self::is($t, $next, 44) || self::is($t, $next, \__McTokId::T_CLOSE_TAG)) {
                break;
            }
            $det = self::blockEdge($t->k[$next]);
            if ($det === null) {
                $index = $next;
                continue;
            }
            if ($det[1] === 0) {
                break;
            }
            $index = $t->blockEnd($next, TypeWalk::closer($det[0]));
        }
        return [$start, $index];
    }

    /** @return int[] */
    private function beforeRange(FlatTokens $t, int $index): array
    {
        $prev = $t->meaningful($index, -1);
        $pk = $t->k[$prev];
        $pcode = $t->i[$prev] !== -1;
        if ($pcode && ($pk === 44 || self::isOpenEdge($pk))) {
            return [$index, $index];
        }
        $end = $prev;
        $index = $prev;
        while ($prev >= 0 && $t->i[$prev] !== -1 && self::chainKind($t->k[$prev])) {
            $det = self::blockEdge($t->k[$prev]);
            if ($det !== null) {
                $blockStart = $t->blockStart($prev, $det[0]);
                if ($t->k[$prev] === 41) {
                    $bb = $t->meaningful($blockStart, -1);
                    if ($bb >= 0 && ($t->k[$bb] === \__McTokId::T_IF || $t->k[$bb] === \__McTokId::T_ELSE || $t->k[$bb] === \__McTokId::T_ELSEIF || $t->k[$bb] === \__McTokId::T_FOR
                        || $t->k[$bb] === \__McTokId::T_FOREACH || $t->k[$bb] === \__McTokId::T_WHILE)) {
                        break;
                    }
                }
                $prev = $blockStart;
            }
            $index = $prev;
            $prev = $t->meaningful($prev, -1);
        }
        if ($prev >= 0 && self::is($t, $prev, \__McTokId::T_OBJECT_OPERATOR)) {
            $index = $this->beforeRange($t, $prev)[0];
        } elseif ($prev >= 0 && self::is($t, $prev, \__McTokId::T_DOUBLE_COLON)) {
            $index = $this->beforeRange($t, $t->meaningful($prev, -1))[0];
        }
        return [$index, $end];
    }

    private static function chainKind(int $k): bool
    {
        return $k === 36 || $k === 93 || $k === 41 || $k === CT::ARRAY_INDEX_BRACE_CLOSE || $k === CT::DYNAMIC_PROP_BRACE_CLOSE || $k === CT::DYNAMIC_VAR_BRACE_CLOSE
            || $k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE;
    }

    /** a block's opening edge (any type) or the `,` stand-in: Tokens::getBlockEdgeDefinitions starts */
    private static function isOpenEdge(int $k): bool
    {
        $d = self::blockEdge($k);
        return $d !== null && $d[1] === 1;
    }

    private function belongsToSwitchOrAlt(FlatTokens $t, int $q): bool
    {
        $i = $t->meaningful($q, -1);
        if ($i >= 0 && $t->k[$i] === \__McTokId::T_DEFAULT) {
            return true;
        }
        $i = $t->meaningful($i, -1);
        if ($i >= 0 && $t->k[$i] === \__McTokId::T_CASE) {
            return true;
        }
        return $this->an->belongsToAlternativeSyntax($t, $q);
    }
}
