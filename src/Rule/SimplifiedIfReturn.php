<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\SimplifiedIfReturnFixer (priority 1). `if (c) { return true; } return false;` -> `return (bool) c;`, and
 * with the booleans swapped `return !c;` (the `if` token is replaced, the braces / returns / booleans / second `;` cleared). Whole-file FlatTokens port, last if first.
 */
final class SimplifiedIfReturn extends FlatRule
{
    public function name(): string
    {
        return 'simplified_if_return';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        $if = false;
        $ret = false;
        for ($i = 0; $i + 2 < $s->n; $i++) {
            $k = $s->kind[$i];
            $if = $if || $k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF;
            if ($k === \__McTokId::T_RETURN && $s->kind[$i + 1] === \__McTokId::T_STRING && $s->kind[$i + 2] === 59) {
                $w = $s->text($i + 1);
                $ret = $ret || $w === 'true' || $w === 'false';
            }
        }
        return $if && $ret;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $slices = [];
        for ($if = $t->count() - 1; $if >= 0; --$if) {
            if ($t->i[$if] === -1 || ($t->k[$if] !== \__McTokId::T_IF && $t->k[$if] !== \__McTokId::T_ELSEIF)) {
                continue;
            }
            $prev = $t->meaningful($if, -1);
            if (self::is($t, $prev, 41)) {
                continue;
            }
            $start = self::nextOf($t, $if, 40);
            $end = $t->blockEnd($start, 41);
            $first = $t->meaningful($end, 1);
            $match = $this->match($t, $first);
            if ($match === null) {
                continue;
            }
            $negative = $match[0] === 1;
            $open = self::is($t, $match[1], 123);
            $close = false;
            for ($x = 1; $x < \count($match); $x++) {
                $close = $close || self::is($t, $match[$x], 125);
            }
            if ($open !== $close) {
                $s->abort = true; // an unmatched brace goes (`if (c) return true;` closing a loop body): upstream's result does not lint, the file stays as it is
                return;
            }
            $clear = \array_slice($match, 1);
            $lastSemicolon = \array_pop($clear);
            \rsort($clear);
            foreach ($clear as $q) {
                self::clearMerge($t, $q);
            }
            self::removeWs($t, $lastSemicolon, -1);
            $slices[$if] = [FlatTokens::code(\__McTokId::T_RETURN, 'return'), FlatTokens::ws(' '),
                $negative ? FlatTokens::code(33, '!') : FlatTokens::code(\__McTokId::T_BOOL_CAST, '(bool)')];
            $t->clearAt($if);
        }
        if ($slices !== []) {
            $t->insertSlices($slices);
        }
    }

    /** @return int[]|null [isNegative, ...indices to clear (the last is the final `;`)] */
    private function match(FlatTokens $t, int $start): ?array
    {
        $indices = [];
        if (self::is($t, $start, 123)) {
            $indices[] = $start;
            $start = $t->meaningful($start, 1);
            if ($start < 0) {
                return null;
            }
        }
        if (!self::is($t, $start, \__McTokId::T_RETURN)) {
            return null;
        }
        $indices[] = $start;
        $bool1 = $t->meaningful($start, 1);
        if ($bool1 < 0 || !self::is($t, $bool1, \__McTokId::T_STRING) || ($t->content($bool1) !== 'true' && $t->content($bool1) !== 'false')) {
            return null;
        }
        $value1 = $t->content($bool1);
        $semi1 = $t->meaningful($bool1, 1);
        if ($semi1 < 0 || $t->i[$semi1] === -1 || $t->content($semi1) !== ';') {
            return null;
        }
        $indices[] = $bool1;
        $indices[] = $semi1;
        $next = $t->meaningful($semi1, 1);
        if ($next < 0) {
            return null;
        }
        if (self::is($t, $next, 125)) {
            $indices[] = $next;
            $next = $t->meaningful($next, 1);
            if ($next < 0) {
                return null;
            }
        }
        $negative = $value1 === 'false';
        if (!self::is($t, $next, \__McTokId::T_RETURN)) {
            return null;
        }
        $indices[] = $next;
        $bool2 = $t->meaningful($next, 1);
        if ($bool2 < 0 || !self::is($t, $bool2, \__McTokId::T_STRING) || $t->content($bool2) !== ($negative ? 'true' : 'false')) {
            return null;
        }
        $semi2 = $t->meaningful($bool2, 1);
        if ($semi2 < 0 || $t->i[$semi2] === -1 || $t->content($semi2) !== ';') {
            return null;
        }
        $indices[] = $bool2;
        $indices[] = $semi2;
        return \array_merge([$negative ? 1 : 0], $indices);
    }
}
