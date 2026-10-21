<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoSuperfluousElseifFixer (priority 39, AbstractNoUselessElseFixer). An `elseif` / `else if` whose preceding
 * if / elseif blocks all end in break / continue / exit / goto / return / throw becomes `if` (the `else` goes with its whitespace merged, a line break is
 * restored before the `if`). Whole-file FlatTokens port: the walk sees its own earlier conversions (a converted `if` ends the chain of the next one).
 */
final class NoSuperfluousElseif extends FlatRule
{
    public function name(): string
    {
        return 'no_superfluous_elseif';
    }

    public function priority(): int
    {
        return 39;
    }

    protected function candidate(Source $s): bool
    {
        // an `elseif` / `else if` whose preceding block can end the flow: a `;` or a `}` of a block that holds a return / throw / break / continue / exit / goto
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if (!($k === \__McTokId::T_ELSEIF || ($k === \__McTokId::T_ELSE && $i + 1 < $s->n && $s->kind[$i + 1] === \__McTokId::T_IF))) {
                continue;
            }
            $p = $i - 1;
            if ($s->kind[$p] !== 125 || $s->ct[$p] !== 0) {
                return true;
            }
            $o = $s->pair[$p];
            if ($o < 0) {
                return true;
            }
            for ($j = $o + 1; $j < $p; $j++) {
                $x = $s->kind[$j];
                if ($x === \__McTokId::T_RETURN || $x === \__McTokId::T_THROW || $x === \__McTokId::T_BREAK || $x === \__McTokId::T_CONTINUE || $x === \__McTokId::T_EXIT
                    || $x === \__McTokId::T_GOTO) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = 0; $index < $t->count(); $index++) {
            if ($this->isElseif($t, $index) && $this->isSuperfluousElse($t, $index)) {
                $this->convert($t, $index);
            }
        }
    }

    private function isElseif(FlatTokens $t, int $index): bool
    {
        if (self::is($t, $index, \__McTokId::T_ELSEIF)) {
            return true;
        }
        if (!self::is($t, $index, \__McTokId::T_ELSE)) {
            return false;
        }
        $nx = $t->meaningful($index, 1);
        return $nx >= 0 && self::is($t, $nx, \__McTokId::T_IF);
    }

    private function convert(FlatTokens $t, int $index): void
    {
        if (self::is($t, $index, \__McTokId::T_ELSE)) {
            self::clearMerge($t, $index);
        } else {
            $t->k[$index] = \__McTokId::T_IF;
            $t->c[$index] = 'if';
            $t->i[$index] = FlatTokens::NEW;
        }
        $whitespace = '';
        for ($previous = $index - 1; $previous > 0; --$previous) {
            if ($t->isWs($previous) && Preg::match('/(\R\N*)$/', $t->c[$previous], $m)) {
                $whitespace = (string) $m[1];
                break;
            }
        }
        if ($whitespace === '') {
            return;
        }
        if (!$t->isWs($index - 1)) {
            $t->insertAt($index, [FlatTokens::ws($whitespace)]);
        } elseif (!Preg::test('/\R/', $t->c[$index - 1])) {
            $t->c[$index - 1] = $whitespace;
        }
    }

    /** Tokens::getPrevTokenOfKind for effective ids; -1 when there is none */
    private static function prevOfKind(FlatTokens $t, int $q, array $ids): int
    {
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && \in_array($t->k[$j], $ids, true)) {
                return $j;
            }
        }
        return -1;
    }

    private function isSuperfluousElse(FlatTokens $t, int $index): bool
    {
        $start = $index;
        do {
            [$start, $end] = $this->previousBlock($t, $start);
            if ($start < 0 || $end < 0) {
                return false;
            }
            $previous = $end;
            if (self::is($t, $previous, 125)) {
                $previous = $t->meaningful($previous, -1);
            }
            if ($previous < 0 || !self::is($t, $previous, 59)) {
                return false;
            }
            $pp = $t->meaningful($previous, -1);
            if ($pp >= 0 && self::is($t, $pp, 123)) {
                return false;
            }
            $cand = self::prevOfKind($t, $previous, [59, \__McTokId::T_BREAK, \__McTokId::T_CLOSE_TAG, \__McTokId::T_CONTINUE, \__McTokId::T_EXIT, \__McTokId::T_GOTO,
                \__McTokId::T_IF, \__McTokId::T_RETURN, \__McTokId::T_THROW]);
            if ($cand < 0) {
                return false;
            }
            $ck = $t->k[$cand];
            if ($ck === 59 || $ck === \__McTokId::T_CLOSE_TAG || $ck === \__McTokId::T_IF) {
                return false;
            }
            if ($ck === \__McTokId::T_THROW) {
                $pi = $t->meaningful($cand, -1);
                if ($pi < 0 || !(self::is($t, $pi, 59) || self::is($t, $pi, 123))) {
                    return false;
                }
            }
            if ($this->inConditional($t, $cand, $start) || $this->inConditionWithoutBraces($t, $cand, $start)) {
                return false;
            }
        } while (!self::is($t, $start, \__McTokId::T_IF));
        return true;
    }

    /** @return array{0:int,1:int} */
    private function previousBlock(FlatTokens $t, int $index): array
    {
        $close = $t->meaningful($index, -1);
        if ($close < 0) {
            return [-1, -1];
        }
        $previous = $close;
        if (self::is($t, $close, 125)) {
            $previous = $t->blockStart($close, 123);
            if ($previous < 0) {
                return [-1, -1];
            }
        }
        $open = self::prevOfKind($t, $previous, [\__McTokId::T_IF, \__McTokId::T_ELSE, \__McTokId::T_ELSEIF]);
        if ($open >= 0 && self::is($t, $open, \__McTokId::T_IF)) {
            $e = $t->meaningful($open, -1);
            if ($e >= 0 && self::is($t, $e, \__McTokId::T_ELSE)) {
                $open = $e;
            }
        }
        return [$open, $close];
    }

    private function inConditional(FlatTokens $t, int $index, int $lower): bool
    {
        $c = self::prevOfKind($t, $index, [41, 59, 58]);
        if ($c < 0) {
            return false;
        }
        if ($t->k[$c] === 58) {
            return true;
        }
        if ($t->k[$c] !== 41) {
            return false;
        }
        $open = $t->blockStart($c, 40);
        return $open >= 0 && $t->meaningful($open, -1) > $lower;
    }

    private function inConditionWithoutBraces(FlatTokens $t, int $index, int $lower): bool
    {
        do {
            if ($index < 0) {
                return false;
            }
            if ($t->i[$index] === -1) {
                $index = $t->meaningful($index, -1);
                if ($index < 0) {
                    return false;
                }
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_ELSE) {
                return true;
            }
            if ($k === 59) {
                return false;
            }
            if ($k === 123) {
                $index = $t->meaningful($index, -1);
                if ($index >= 0 && self::is($t, $index, \__McTokId::T_DO)) {
                    --$index;
                    continue;
                }
                if ($index < 0 || !self::is($t, $index, 41)) {
                    return false;
                }
                $index = $t->blockStart($index, 40);
                $index = $index < 0 ? -1 : $t->meaningful($index, -1);
                if ($index >= 0 && (self::is($t, $index, \__McTokId::T_IF) || self::is($t, $index, \__McTokId::T_ELSEIF))) {
                    return false;
                }
            } elseif ($k === 41) {
                $index = $t->blockStart($index, 40);
                $index = $index < 0 ? -1 : $t->meaningful($index, -1);
            } else {
                --$index;
            }
        } while ($index > $lower);
        return false;
    }
}
