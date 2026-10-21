<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\ImplodeCallFixer (priority 37, risky). `implode($a)` -> `implode('', $a)`; `implode($a, ', ')` -> `implode(', ', $a)` (the
 * first argument is not a string literal, the second one is). Whole-file FlatTokens port, last call first.
 */
final class ImplodeCall extends FlatRule
{
    public function name(): string
    {
        return 'implode_call';
    }

    public function priority(): int
    {
        return 37;
    }

    /** a global-call-agnostic superset of the calls that change: one argument, or two with a string literal second and a non-literal first */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i + 2 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->kind[$i + 1] !== 40 || $s->end[$i] - $s->start[$i] !== 7 || \strtolower($s->text($i)) !== 'implode') {
                continue;
            }
            $close = $s->pair[$i + 1];
            if ($close <= $i + 2) {
                continue;
            }
            $commas = [];
            for ($j = $i + 2; $j < $close; $j++) {
                $k = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if (\Fmt\TypeWalk::closer($k) >= 0 && $s->pair[$j] > $j) {
                    $j = $s->pair[$j];
                    continue;
                }
                if ($k === 44 && $j + 1 < $close) {
                    $commas[] = $j;
                }
            }
            if (\count($commas) === 0) {
                return true;
            }
            if (\count($commas) !== 1) {
                continue;
            }
            $c = $commas[0];
            $last = $s->kind[$close - 1] === 44 ? $close - 2 : $close - 1;
            $secondString = $last === $c + 1 && $s->kind[$last] === \__McTokId::T_CONSTANT_ENCAPSED_STRING;
            $firstString = $c === $i + 3 && $s->kind[$i + 2] === \__McTokId::T_CONSTANT_ENCAPSED_STRING;
            if ($secondString && !$firstString) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, int> trimmed start => trimmed end of each argument */
    private static function indices(FlatTokens $t, int $fn): array
    {
        $open = self::nextOf($t, $fn, 40);
        $close = $t->blockEnd($open, 41);
        $out = [];
        foreach (self::callArguments($t, $open, $close) as $a => $b) {
            $out[$t->meaningful($a - 1, 1)] = $t->meaningful($b + 1, -1);
        }
        return $out;
    }

    /** @return array{0:int,1:string,2:int} a copy of item $q as an inserted item */
    public static function cloneItem(FlatTokens $t, int $q): array
    {
        if ($t->i[$q] >= 0) {
            return [$t->k[$q], $t->content($q), FlatTokens::NEW];
        }
        return [$t->k[$q], $t->c[$q], $t->i[$q]];
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if ($t->i[$q] === -1 || $t->k[$q] !== \__McTokId::T_STRING || \strtolower($t->content($q)) !== 'implode') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $q)) {
                continue;
            }
            $args = self::indices($t, $q);
            $cnt = \count($args);
            if ($cnt === 1) {
                $starts = \array_keys($args);
                $t->insertAt($starts[0], [FlatTokens::code(\__McTokId::T_CONSTANT_ENCAPSED_STRING, "''"), FlatTokens::code(44, ','), FlatTokens::ws(' ')]);
                continue;
            }
            if ($cnt === 2) {
                $starts = \array_keys($args);
                $first = $starts[0];
                $second = $starts[1];
                if ($t->k[$first] === \__McTokId::T_CONSTANT_ENCAPSED_STRING && $t->i[$first] !== -1) {
                    continue;
                }
                if (!($t->k[$second] === \__McTokId::T_CONSTANT_ENCAPSED_STRING && $t->i[$second] !== -1)) {
                    continue;
                }
                $firstEnd = $args[$first];
                $new = [];
                for ($i = $first; $i <= $firstEnd; $i++) {
                    $new[] = self::cloneItem($t, $i);
                    $t->clearAt($i);
                }
                $t->insertAt($first, [self::cloneItem($t, $second)]);
                $second++;
                $t->clearAt($second);
                $t->insertAt($second, $new);
            }
        }
    }
}
