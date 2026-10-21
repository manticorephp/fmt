<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\YieldFromArrayToYieldsFixer (priority 0, risky). A statement `yield from [a, b];` (or `array(..)`) becomes `yield a; yield b;` (the
 * array must not be empty or contain another `yield from`). Whole-file FlatTokens port: upstream's generator is evaluated lazily between the edits, the `yield ` insertions are
 * applied at the end (`insertSlices`).
 */
final class YieldFromArrayToYields extends FlatRule
{
    public function name(): string
    {
        return 'yield_from_array_to_yields';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_YIELD_FROM) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, bool> $inserts */
        $inserts = [];
        $count = $t->count();
        $index = 0;
        while (++$index < $count) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_YIELD_FROM) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if (!(self::is($t, $prev, 59) || self::is($t, $prev, 123) || self::is($t, $prev, 125) || self::is($t, $prev, \__McTokId::T_OPEN_TAG))) {
                continue;
            }
            $arr = $t->meaningful($index, 1);
            if ($arr < 0 || !($t->i[$arr] !== -1 && ($t->k[$arr] === \__McTokId::T_ARRAY || $t->k[$arr] === CT::ARRAY_BRACKET_OPEN))) {
                continue;
            }
            if ($t->k[$arr] === \__McTokId::T_ARRAY) {
                $start = self::nextOf($t, $arr, 40);
                $end = $t->blockEnd($start, 41);
            } else {
                $start = $arr;
                $end = $t->blockEnd($start, CT::ARRAY_BRACKET_CLOSE);
            }
            if ($end === $t->meaningful($start, 1)) {
                continue;
            }
            $nested = false;
            for ($j = $start; $j <= $end; $j++) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_YIELD_FROM) {
                    $nested = true;
                    break;
                }
            }
            if ($nested) {
                continue;
            }
            self::clearMerge($t, $index);
            if (self::is($t, $start, 40)) {
                $ps = $t->meaningful($start, -1);
                self::clearMerge($t, $ps);
            }
            self::clearMerge($t, $start);
            self::clearMerge($t, $end);
            $trailing = false;
            $start = $t->meaningful($start, 1);
            $inserts[$start] = true;
            $last = $t->meaningful($end, -1);
            for ($j = $start; $j <= $last; $j++) {
                $det = $t->i[$j] === -1 ? null : self::blockEdge($t->k[$j]);
                if ($det !== null && $det[1] === 1) {
                    $j = $t->blockEnd($j, TypeWalk::closer($det[0]));
                    continue;
                }
                if (!self::is($t, $j, 44)) {
                    continue;
                }
                $nextItem = $t->meaningful($j, 1);
                if ($nextItem < $end) {
                    $inserts[$nextItem] = true;
                    self::setCode($t, $j, 59, ';');
                } else {
                    $trailing = true;
                    self::setCode($t, $j, 59, ';');
                }
            }
            if ($trailing) {
                self::clearMerge($t, $t->meaningful($end, 1));
            }
        }
        \krsort($inserts);
        foreach ($inserts as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(\__McTokId::T_YIELD, 'yield'), FlatTokens::ws(' ')]);
        }
    }
}
