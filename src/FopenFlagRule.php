<?php

declare(strict_types=1);

namespace Fmt;

/** AbstractFopenFlagFixer: every global `fopen` call with 2..4 arguments hands the span of its second argument to fixFlag(). */
abstract class FopenFlagRule extends FlatRule
{
    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['fopen']);
    }

    abstract protected function fixFlag(FlatTokens $t, int $a, int $b): void;

    protected function run(Source $s, FlatTokens $t): void
    {
        $index = 0;
        $end = $t->count() - 1;
        while (true) {
            $c = $this->findCall($s, $t, 'fopen', $index, $end);
            if ($c === null) {
                break;
            }
            $index = $c[1];
            $args = self::callArguments($t, $index, $c[2]);
            $cnt = \count($args);
            if ($cnt < 2 || $cnt > 4) {
                continue;
            }
            $starts = \array_keys($args);
            $this->fixFlag($t, $starts[1], $args[$starts[1]]);
        }
    }

    /** the only meaningful item of the span when it is a string literal, else -1 */
    protected static function soleString(FlatTokens $t, int $a, int $b): int
    {
        $flag = -1;
        for ($i = $a; $i <= $b; $i++) {
            if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                continue;
            }
            if ($flag >= 0) {
                return -1;
            }
            $flag = $i;
        }
        if ($flag < 0 || $t->i[$flag] === -1 || $t->k[$flag] !== \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
            return -1;
        }
        return $flag;
    }

    protected static function validMode(string $mode): bool
    {
        $len = \strlen($mode);
        if ($len < 1 || $len > 13) {
            return false;
        }
        $valid = ['a' => true, 'b' => true, 'c' => true, 'e' => true, 'r' => true, 't' => true, 'w' => true, 'x' => true];
        if (!isset($valid[$mode[0]])) {
            return false;
        }
        unset($valid[$mode[0]]);
        for ($i = 1; $i < $len; $i++) {
            if (isset($valid[$mode[$i]])) {
                unset($valid[$mode[$i]]);
                continue;
            }
            $p = $mode[$i - 1];
            if ($mode[$i] !== '+' || ($p !== 'a' && $p !== 'c' && $p !== 'r' && $p !== 'w' && $p !== 'x')) {
                return false;
            }
        }
        return true;
    }
}
