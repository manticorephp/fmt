<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\RegularCallableCallFixer (priority 2, risky). `call_user_func('foo', 1)` -> `foo(1)`, `call_user_func(function () {}, 1)` -> `(function () {})(1)`,
 * `call_user_func($f, 1)` -> `$f(1)` (`call_user_func_array($f, $a)` -> `$f(...$a)`). Whole-file FlatTokens port, last call first; a call without arguments ends the scan (upstream's `return`).
 */
final class RegularCallableCall extends FlatRule
{
    public function name(): string
    {
        return 'regular_callable_call';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['call_user_func', 'call_user_func_array']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index > 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING) {
                continue;
            }
            $lower = \strtolower($t->content($index));
            if ($lower !== 'call_user_func' && $lower !== 'call_user_func_array') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $open = $t->meaningful($index, 1);
            $close = $t->blockEnd($open, 41);
            $args = self::callArguments($t, $open, $close);
            if (\count($args) < 1) {
                return;
            }
            $this->process($s, $t, $index, $lower === 'call_user_func_array', $args);
        }
    }

    /** @param array<int, int> $args */
    private function process(Source $s, FlatTokens $t, int $index, bool $array, array $args): void
    {
        $first = $t->meaningful($t->meaningful($index, 1), 1);
        if ($first < 0 || $t->i[$first] === -1) {
            return;
        }
        $fk = $t->k[$first];
        if ($fk === \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
            $after = $t->meaningful($first, 1);
            if (!(self::is($t, $after, 44) || self::is($t, $after, 41))) {
                return;
            }
            $content = $t->content($first);
            if (\strlen($content) < 3 || $content[0] === 'b' || $content[0] === 'B') {
                return;
            }
            $inner = \substr($content, 1, -1);
            if ($inner !== \trim($inner)) {
                return;
            }
            $p = Source::parse('<?php ' . \substr(\str_replace('\\\\', '\\', $content), 1, -1) . '();');
            $items = [];
            for ($j = 1; $j < $p->n - 3; $j++) {
                if ($j > 1 && $p->gapBefore[$j] !== '') {
                    $items[] = FlatTokens::ws($p->gapBefore[$j]);
                }
                $items[] = FlatTokens::code($p->ct[$j] !== 0 ? $p->ct[$j] : $p->kind[$j], $p->text($j));
            }
            $this->replace($t, $index, $items, $first, $first, $array);
            return;
        }
        if ($fk === \__McTokId::T_FUNCTION || ($fk === \__McTokId::T_STATIC && self::is($t, $t->meaningful($first, 1), \__McTokId::T_FUNCTION))) {
            $bo = self::nextOf($t, $first, 123);
            $end = $bo < 0 ? -1 : $t->blockEnd($bo, 125);
            if ($end < 0) {
                $s->abort = true;
                return;
            }
            $items = [FlatTokens::code(40, '(')];
            for ($j = $first; $j <= $end; $j++) {
                $items[] = \Fmt\Rule\ImplodeCall::cloneItem($t, $j);
            }
            $items[] = FlatTokens::code(41, ')');
            $this->replace($t, $index, $items, $first, $end, $array);
            return;
        }
        if ($fk === \__McTokId::T_VARIABLE) {
            $starts = \array_keys($args);
            $firstEnd = $args[$starts[0]];
            foreach ($args as $as => $ae) {
                if ($firstEnd === $ae) {
                    continue;
                }
                for ($i = $as; $i <= $ae; $i++) {
                    if ($t->i[$i] !== -1 && $t->k[$i] === $fk && $t->content($i) === $t->content($first)) {
                        return;
                    }
                }
            }
            $items = [];
            for ($j = $first; $j <= $firstEnd; $j++) {
                $items[] = \Fmt\Rule\ImplodeCall::cloneItem($t, $j);
            }
            $complex = false;
            for ($x = \count($items) - 1; $x >= 0; $x--) {
                $k = $items[$x][0];
                if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT || $k === \__McTokId::T_VARIABLE) {
                    continue;
                }
                if ($k === 93 || $k === \Fmt\CT::ARRAY_INDEX_BRACE_CLOSE) {
                    // an index `]` / `}`: jump back over the subscript to its opener
                    $open = $k === 93 ? 91 : \Fmt\CT::ARRAY_INDEX_BRACE_OPEN;
                    $depth = 0;
                    for ($y = $x; $y >= 0; $y--) {
                        $ky = $items[$y][0];
                        if ($ky === $k) {
                            $depth++;
                        } elseif ($ky === $open) {
                            $depth--;
                            if ($depth === 0) {
                                $x = $y;
                                break;
                            }
                        }
                    }
                    continue;
                }
                $complex = true;
                break;
            }
            if ($complex) {
                $items[] = FlatTokens::code(41, ')');
                \array_unshift($items, FlatTokens::code(40, '('));
            }
            $this->replace($t, $index, $items, $first, $firstEnd, $array);
        }
    }

    /** @param array<int, array{0:int,1:string,2:int}> $items */
    private function replace(FlatTokens $t, int $call, array $items, int $startArg, int $endArg, bool $array): void
    {
        for ($i = $startArg; $i <= $endArg; $i++) {
            $t->clearAt($i);
        }
        $after = $t->meaningful($endArg, 1);
        if (self::is($t, $after, 44)) {
            if ($array) {
                $second = $t->meaningful($after, 1);
                $t->insertAt($second, [FlatTokens::code(\__McTokId::T_ELLIPSIS, '...')]);
                $after = $t->meaningful($endArg, 1);
            }
            $t->clearAt($after);
            self::removeWs($t, $after, 1);
        }
        self::overrideRange($t, $call, $call, $items);
        $prev = $t->meaningful($call, -1);
        if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
            self::clearMerge($t, $prev);
        }
    }
}
