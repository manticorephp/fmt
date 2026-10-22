<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\ReturnToYieldFromFixer (priority 1). In a function declared `: iterable`, a `return [..];` / `return array(..);`
 * that is the last statement before the function's `}` becomes `yield from [..];`. Whole-file FlatTokens port.
 */
final class ReturnToYieldFrom extends FlatRule
{
    public function name(): string
    {
        return 'return_to_yield_from';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        $f = false;
        $r = false;
        $a = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $f = $f || $k === \__McTokId::T_FUNCTION;
            $r = $r || $k === \__McTokId::T_RETURN;
            $a = $a || $k === \__McTokId::T_ARRAY || $s->ct[$i] === CT::ARRAY_BRACKET_OPEN;
        }
        return $f && $r && $a;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $m = $t->count();
        for ($q = 0; $q < $m; $q++) {
            if (self::is($t, $q, \__McTokId::T_RETURN) && $this->shouldBeFixed($t, $q)) {
                self::setCode($t, $q, \__McTokId::T_YIELD_FROM, 'yield from');
            }
        }
    }

    private function shouldBeFixed(FlatTokens $t, int $return): bool
    {
        $start = $t->meaningful($return, 1);
        if ($start < 0 || !(self::is($t, $start, \__McTokId::T_ARRAY) || self::is($t, $start, CT::ARRAY_BRACKET_OPEN))) {
            return false;
        }
        if (self::is($t, $start, CT::ARRAY_BRACKET_OPEN)) {
            $end = $t->blockEnd($start, CT::ARRAY_BRACKET_CLOSE);
        } else {
            $open = self::nextOf($t, $start, 40);
            $end = $t->blockEnd($open, 41);
        }
        $fnEnd = $end;
        do {
            $fnEnd = $t->meaningful($fnEnd, 1);
        } while ($fnEnd >= 0 && self::is($t, $fnEnd, 59));
        if ($fnEnd < 0 || !self::is($t, $fnEnd, 125)) {
            return false;
        }
        $fnStart = $t->blockStart($fnEnd, 123);
        $type = $t->meaningful($fnStart, -1);
        if ($type < 0 || !self::is($t, $type, \__McTokId::T_STRING) || \strtolower($t->content($type)) !== 'iterable') {
            return false;
        }
        return self::is($t, $t->meaningful($type, -1), CT::TYPE_COLON);
    }
}
