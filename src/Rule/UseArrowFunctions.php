<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\UseArrowFunctionsFixer (priority 32, risky). `function ($a) use ($b) { return $a + $b; }` -> `fn ($a) => $a + $b` for a closure whose
 * body is one `return` statement (no by-reference `use`, no include / require next to a `use`). Whole-file FlatTokens port, last closure first.
 */
final class UseArrowFunctions extends FlatRule
{
    public function name(): string
    {
        return 'use_arrow_functions';
    }

    public function priority(): int
    {
        return 32;
    }

    /** a closure whose body starts with `return` and whose first top-level `;` is the last token of the body (a superset of the rewrites: `use` by reference etc. are left to the real pass) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i + 2 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_FUNCTION || $s->ct[$i] !== 0 || !Analyzer::isLambda($s, $i)) {
                continue;
            }
            $a = $i + 1;
            if ($s->ct[$a] === CT::RETURN_REF) {
                $a++;
            }
            $j = $s->pair[$a] + 1;
            while ($j < $n && !($s->kind[$j] === 123 && $s->ct[$j] === 0)) {
                if ($s->kind[$j] === 59) {
                    break;
                }
                $j++;
            }
            if ($j + 1 >= $n || $s->kind[$j] !== 123 || $s->kind[$j + 1] !== \__McTokId::T_RETURN) {
                continue;
            }
            $close = $s->pair[$j];
            for ($x = $j + 2; $x < $close; $x++) {
                $k = $s->ct[$x] !== 0 ? $s->ct[$x] : $s->kind[$x];
                if (self::blockEdge($k) !== null && $s->pair[$x] > $x) {
                    $x = $s->pair[$x];
                    continue;
                }
                if ($k === 59) {
                    if ($x === $close - 1) {
                        return true;
                    }
                    break;
                }
            }
        }
        return false;
    }
    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index > 0; $index--) {
            if ($t->i[$index] !== -1 && $t->k[$index] === CT::ATTRIBUTE_CLOSE) {
                $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
                continue;
            }
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_FUNCTION || $t->i[$index] < 0 || !Analyzer::isLambda($s, $t->i[$index])) {
                continue;
            }
            $pStart = $t->meaningful($index, 1);
            if (self::is($t, $pStart, CT::RETURN_REF)) {
                $pStart = $t->meaningful($pStart, 1);
            }
            $pEnd = $t->blockEnd($pStart, 41);
            $next = $t->meaningful($pEnd, 1);
            $useStart = -1;
            $useEnd = -1;
            if (self::is($t, $next, CT::USE_LAMBDA)) {
                $useStart = $next;
                if ($t->k[$useStart - 1] === FlatTokens::WS && $t->c[$useStart - 1] !== '') {
                    $useStart--;
                }
                $next = $t->meaningful($next, 1);
                $byRef = false;
                while (!self::is($t, $next, 41)) {
                    if ($t->i[$next] !== -1 && ($t->k[$next] === 38 || $t->k[$next] === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
                        || $t->k[$next] === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                        $byRef = true;
                        break;
                    }
                    $next = $t->meaningful($next, 1);
                }
                if ($byRef) {
                    continue;
                }
                $useEnd = $next;
                $next = $t->meaningful($next, 1);
            }
            $braceOpen = self::is($t, $next, 123) ? $next : self::nextOf($t, $next, 123);
            $return = $braceOpen + 1;
            if ($t->k[$return] === FlatTokens::WS && $t->c[$return] !== '') {
                $return++;
            }
            if (!self::is($t, $return, \__McTokId::T_RETURN)) {
                continue;
            }
            $semi = -1;
            $m = $t->count();
            for ($j = $return + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59)) {
                    $semi = $j;
                    break;
                }
            }
            if ($semi < 0 || !self::is($t, $semi, 59)) {
                continue;
            }
            $braceClose = $semi + 1;
            if ($t->k[$braceClose] === FlatTokens::WS && $t->c[$braceClose] !== '') {
                $braceClose++;
            }
            if (!self::is($t, $braceClose, 125)) {
                continue;
            }
            if ($useStart >= 0) {
                $inc = false;
                for ($i = $return; $i < $semi; $i++) {
                    $k = $t->i[$i] === -1 ? -1 : $t->k[$i];
                    if ($k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE) {
                        $inc = true;
                        break;
                    }
                }
                if ($inc) {
                    continue;
                }
            }
            $insert = [FlatTokens::code(\__McTokId::T_DOUBLE_ARROW, '=>')];
            if ($t->meaningful($return, 1) === $semi) {
                $insert[] = FlatTokens::ws(' ');
                $insert[] = FlatTokens::code(\__McTokId::T_STRING, 'null');
            }
            for ($i = $semi; $i <= $braceClose - 1; $i++) {
                $t->clearAt($i);
            }
            self::clearMerge($t, $braceClose);
            for ($i = $braceOpen + 1; $i <= $return; $i++) {
                $t->clearAt($i);
            }
            self::overrideRange($t, $braceOpen, $braceOpen, $insert);
            if ($useStart >= 0) {
                for ($i = $useStart; $i <= $useEnd; $i++) {
                    $t->clearAt($i);
                }
            }
            self::setCode($t, $index, \__McTokId::T_FN, 'fn');
        }
    }
}
