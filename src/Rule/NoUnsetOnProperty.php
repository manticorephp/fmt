<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\NoUnsetOnPropertyFixer (priority 25, risky). `unset($this->a, $b)` -> `$this->a = null; unset($b)`: every argument that is a property
 * (`$var->name` or `Class::$name`) becomes an assignment of `null`, the others are regrouped into `unset(..)` calls. Whole-file FlatTokens port, last `unset` first.
 */
final class NoUnsetOnProperty extends FlatRule
{
    public function name(): string
    {
        return 'no_unset_on_property';
    }

    public function priority(): int
    {
        return 25;
    }

    /** an `unset(` whose parentheses hold a `->` or `::` (a superset of the arguments that are properties) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_UNSET || $s->kind[$i + 1] !== 40) {
                continue;
            }
            $close = $s->pair[$i + 1];
            for ($j = $i + 2; $j < $close; $j++) {
                $k = $s->kind[$j];
                if ($k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_DOUBLE_COLON) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_UNSET) {
                continue;
            }
            $info = $this->unsetsInfo($t, $index);
            $any = false;
            foreach ($info as $u) {
                if ($u[2] === 1) {
                    $any = true;
                }
            }
            if (!$any) {
                continue;
            }
            $last = true;
            for ($x = \count($info) - 1; $x >= 0; $x--) {
                $this->update($t, $info[$x], $last);
                $last = false;
            }
        }
    }

    /** @return array<int, int[]> [start, end, 1 when it is a property to transform, 1 when it is the first argument] */
    private function unsetsInfo(FlatTokens $t, int $index): array
    {
        $start = self::nextOf($t, $index, 40);
        $end = $t->blockEnd($start, 41);
        $first = 1;
        $out = [];
        foreach (self::callArguments($t, $start, $end) as $a => $b) {
            $si = $t->meaningful($a - 1, 1);
            $ei = $t->meaningful($b + 1, -1);
            $out[] = [$si, $ei, $this->isProperty($t, $si, $ei) ? 1 : 0, $first];
            $first = 0;
        }
        return $out;
    }

    private function isProperty(FlatTokens $t, int $index, int $endIndex): bool
    {
        $k = $t->k[$index];
        if ($t->i[$index] !== -1 && $k === \__McTokId::T_VARIABLE) {
            $next = $t->meaningful($index, 1);
            if ($next < 0 || !self::is($t, $next, \__McTokId::T_OBJECT_OPERATOR)) {
                return false;
            }
            $next = $t->meaningful($next, 1);
            $nn = $next >= 0 ? $t->meaningful($next, 1) : -1;
            if ($nn >= 0 && $nn < $endIndex) {
                return false;
            }
            return $next >= 0 && self::is($t, $next, \__McTokId::T_STRING);
        }
        if ($t->i[$index] !== -1 && ($k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STRING)) {
            $m = $t->count();
            $next = -1;
            for ($j = $index + 1; $j < $m; $j++) {
                $kj = $t->k[$j];
                if (!($t->i[$j] !== -1 && ($kj === \__McTokId::T_DOUBLE_COLON || $kj === \__McTokId::T_NS_SEPARATOR || $kj === \__McTokId::T_STRING))) {
                    $next = $j;
                    break;
                }
            }
            $nn = $next >= 0 ? $t->meaningful($next, 1) : -1;
            if ($nn >= 0 && $nn < $endIndex) {
                return false;
            }
            return $next >= 0 && self::is($t, $next, \__McTokId::T_VARIABLE);
        }
        return false;
    }

    /** @param int[] $u */
    private function update(FlatTokens $t, array $u, bool $isLast): void
    {
        $transform = $u[2] === 1;
        $isFirst = $u[3] === 1;
        if ($isFirst && $transform) {
            $brace = -1;
            for ($j = $u[0] - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === 40) {
                    $brace = $j;
                    break;
                }
            }
            $unsetIdx = -1;
            for ($j = $brace - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_UNSET) {
                    $unsetIdx = $j;
                    break;
                }
            }
            self::clearMerge($t, $brace);
            self::clearMerge($t, $unsetIdx);
        }
        if ($isLast && $transform) {
            $brace = self::nextOf($t, $u[1], 41);
            $prev = $t->meaningful($brace, -1);
            if (self::is($t, $prev, 44)) {
                self::clearMerge($t, $prev);
            }
            self::clearMerge($t, $brace);
        }
        if (!$isLast) {
            $comma = self::nextOf($t, $u[1], 44);
            self::setCode($t, $comma, 59, ';');
        }
        if (!$transform && !$isLast) {
            $t->insertAt($u[1] + 1, [FlatTokens::code(41, ')')]);
        }
        if (!$transform && !$isFirst) {
            $t->insertAt($u[0], [FlatTokens::code(\__McTokId::T_UNSET, 'unset'), FlatTokens::code(40, '(')]);
        }
        if ($transform) {
            $t->insertAt($u[1] + 1, [FlatTokens::ws(' '), FlatTokens::code(61, '='), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_STRING, 'null')]);
        }
    }
}
