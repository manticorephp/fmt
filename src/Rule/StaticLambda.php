<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\StaticLambdaFixer (priority 0, risky). A closure / arrow function that cannot reference `$this` (no `$this`, `$$a`, `${..}`, include,
 * eval, `parent`, nested classes aside) becomes `static`, unless it is the direct subject of `->bindTo()` / `->call()`. Whole-file FlatTokens port, last lambda first.
 */
final class StaticLambda extends FlatRule
{
    public function name(): string
    {
        return 'static_lambda';
    }

    public function priority(): int
    {
        return 0;
    }

    /** a non-static lambda whose body shows no reason to keep `$this` (a superset of the rewrites: the binding test and nested classes are left to the real pass) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (($k !== \__McTokId::T_FUNCTION && $k !== \__McTokId::T_FN) || $s->ct[$i] !== 0 || !Analyzer::isLambda($s, $i)) {
                continue;
            }
            if ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_STATIC) {
                continue;
            }
            $a = $i + 1;
            if ($s->ct[$a] === CT::RETURN_REF) {
                $a++;
            }
            $j = $s->pair[$a] + 1;
            if ($k === \__McTokId::T_FUNCTION) {
                while ($j < $n && !($s->kind[$j] === 123 && $s->ct[$j] === 0)) {
                    $j++;
                }
                if ($j >= $n) {
                    continue;
                }
                $to = $s->pair[$j];
            } else {
                while ($j < $n && $s->kind[$j] !== \__McTokId::T_DOUBLE_ARROW) {
                    $j++;
                }
                $j++;
                while ($j < $n) {
                    $kk = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                    if ($kk === 41 || $kk === 93 || $kk === 44 || $kk === 59 || $kk === \__McTokId::T_CLOSE_TAG) {
                        break;
                    }
                    $det = self::blockEdge($kk);
                    if ($det !== null) {
                        if ($det[1] === 0) {
                            break;
                        }
                        $j = $s->pair[$j];
                    }
                    $j++;
                }
                $to = $j - 1;
            }
            if (!$this->bodyMayUseThis($s, $a, $to)) {
                return true;
            }
        }
        return false;
    }

    private function bodyMayUseThis(Source $s, int $from, int $to): bool
    {
        for ($j = $from; $j <= $to && $j < $s->n; $j++) {
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($k === \__McTokId::T_VARIABLE && $s->end[$j] - $s->start[$j] === 5 && \strtolower($s->text($j)) === '$this') {
                return true;
            }
            if ($k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $c === CT::DYNAMIC_VAR_BRACE_OPEN
                || $k === \__McTokId::T_EVAL || $k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                return true;
            }
            if ($k === 36 && $j + 1 < $s->n && $s->kind[$j + 1] === \__McTokId::T_VARIABLE) {
                return true;
            }
            if ($k === \__McTokId::T_STRING && $s->end[$j] - $s->start[$j] === 6 && \strtolower($s->text($j)) === 'parent') {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 4; $index > 0; $index--) {
            if ($t->i[$index] === -1 || !($t->k[$index] === \__McTokId::T_FUNCTION || $t->k[$index] === \__McTokId::T_FN)) {
                continue;
            }
            if ($t->i[$index] < 0 || !Analyzer::isLambda($s, $t->i[$index])) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if (self::is($t, $prev, \__McTokId::T_STATIC)) {
                continue;
            }
            $argsStart = self::nextOf($t, $index, 40);
            $argsEnd = $t->blockEnd($argsStart, 41);
            if ($t->k[$index] === \__McTokId::T_FUNCTION) {
                $lambdaOpen = self::nextOf($t, $argsEnd, 123);
                $lambdaEnd = $t->blockEnd($lambdaOpen, 125);
            } else {
                $lambdaOpen = self::nextOf($t, $argsEnd, \__McTokId::T_DOUBLE_ARROW);
                $lambdaEnd = self::arrowEnd($t, $index);
            }
            if ($this->mayReferenceThis($t, $lambdaOpen, $lambdaEnd)) {
                continue;
            }
            if ($this->directSubjectOfBinding($t, $index, $lambdaEnd)) {
                continue;
            }
            $t->insertAt($index, [FlatTokens::code(\__McTokId::T_STATIC, 'static'), FlatTokens::ws(' ')]);
            $index -= 4;
        }
    }

    private function directSubjectOfBinding(FlatTokens $t, int $lambda, int $end): bool
    {
        $after = $t->meaningful($end, 1);
        if ($after < 0 || !self::is($t, $after, 41)) {
            return false;
        }
        $wrapStart = $t->blockStart($after, 40);
        if ($t->meaningful($wrapStart, 1) !== $lambda) {
            return false;
        }
        $beforeWrap = $t->meaningful($wrapStart, -1);
        if ($beforeWrap >= 0 && $t->i[$beforeWrap] !== -1) {
            $k = $t->k[$beforeWrap];
            if ($k === 41 || $k === 93 || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE || $k === CT::ARRAY_INDEX_BRACE_CLOSE) {
                return false;
            }
        }
        $op = $t->meaningful($after, 1);
        if ($op < 0 || !($t->k[$op] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$op] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
            return false;
        }
        $m = $t->meaningful($op, 1);
        if ($m < 0 || $t->k[$m] !== \__McTokId::T_STRING) {
            return false;
        }
        $n = \strtolower($t->content($m));
        return $n === 'bindto' || $n === 'call';
    }

    private function mayReferenceThis(FlatTokens $t, int $from, int $to): bool
    {
        for ($i = $from; $i <= $to; $i++) {
            if ($t->i[$i] === -1) {
                continue;
            }
            $k = $t->k[$i];
            if ($k === \__McTokId::T_VARIABLE && \strtolower($t->content($i)) === '$this') {
                return true;
            }
            if ($k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE
                || $k === CT::DYNAMIC_VAR_BRACE_OPEN || $k === \__McTokId::T_EVAL) {
                return true;
            }
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                $open = self::nextOf($t, $i, 123);
                $i = $t->meaningful($i, 1);
                if ($i <= $open && $this->mayReferenceThis($t, $i, $open)) {
                    return true;
                }
                $i = $t->blockEnd($open, 125);
                continue;
            }
            if ($k === 36) {
                $nx = $t->meaningful($i, 1);
                if ($nx >= 0 && $t->k[$nx] === \__McTokId::T_VARIABLE) {
                    return true;
                }
            }
            if ($k === \__McTokId::T_STRING && \strtolower($t->content($i)) === 'parent') {
                return true;
            }
        }
        return false;
    }
}
