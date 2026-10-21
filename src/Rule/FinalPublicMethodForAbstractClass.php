<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\FinalPublicMethodForAbstractClassFixer (priority 0, risky). In an `abstract class` every `public` (or `public static`) method that is not abstract,
 * not final and not a magic method gets `final`. Whole-file FlatTokens port, last abstract first.
 */
final class FinalPublicMethodForAbstractClass extends FlatRule
{
    public function name(): string
    {
        return 'final_public_method_for_abstract_class';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        $a = false;
        $p = false;
        $f = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_ABSTRACT) {
                $a = true;
            } elseif ($k === \__McTokId::T_PUBLIC) {
                $p = true;
            } elseif ($k === \__McTokId::T_FUNCTION) {
                $f = true;
            }
        }
        return $a && $p && $f;
    }

    private static function magic(string $lower): bool
    {
        return $lower === '__construct' || $lower === '__destruct' || $lower === '__call' || $lower === '__callstatic' || $lower === '__get' || $lower === '__set' || $lower === '__isset'
            || $lower === '__unset' || $lower === '__sleep' || $lower === '__wakeup' || $lower === '__tostring' || $lower === '__invoke' || $lower === '__set_state' || $lower === '__clone'
            || $lower === '__debuginfo';
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $abstracts = [];
        $m = $t->count();
        for ($q = 0; $q < $m; $q++) {
            if ($t->i[$q] !== -1 && $t->k[$q] === \__McTokId::T_ABSTRACT) {
                $abstracts[] = $q;
            }
        }
        for ($x = \count($abstracts) - 1; $x >= 0; $x--) {
            $ci = -1;
            for ($j = $abstracts[$x] + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === \__McTokId::T_CLASS || $t->k[$j] === \__McTokId::T_FUNCTION)) {
                    $ci = $j;
                    break;
                }
            }
            if ($ci < 0 || $t->k[$ci] !== \__McTokId::T_CLASS) {
                continue;
            }
            $open = self::nextOf($t, $ci, 123);
            $close = $open < 0 ? -1 : $t->blockEnd($open, 125);
            if ($close < 0) {
                $s->abort = true;
                return;
            }
            $this->fixClass($t, $open, $close);
            $m = $t->count();
        }
    }

    private function fixClass(FlatTokens $t, int $open, int $close): void
    {
        for ($index = $close - 1; $index > $open; $index--) {
            if (self::is($t, $index, 125)) {
                $index = $t->blockStart($index, 123);
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_PUBLIC)) {
                continue;
            }
            $next = $t->meaningful($index, 1);
            if (self::is($t, $next, \__McTokId::T_STATIC)) {
                $next = $t->meaningful($next, 1);
            }
            if (!self::is($t, $next, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $next = $t->meaningful($next, 1);
            if ($next >= 0 && self::magic(\strtolower($t->content($next)))) {
                continue;
            }
            $prev = $t->meaningful($index, -1);
            if (self::is($t, $prev, \__McTokId::T_STATIC)) {
                $index = $prev;
                $prev = $t->meaningful($index, -1);
            }
            if (self::is($t, $prev, \__McTokId::T_ABSTRACT) || self::is($t, $prev, \__McTokId::T_FINAL)) {
                $index = $prev;
                continue;
            }
            $t->insertAt($index, [FlatTokens::code(\__McTokId::T_FINAL, 'final'), FlatTokens::ws(' ')]);
        }
    }
}
