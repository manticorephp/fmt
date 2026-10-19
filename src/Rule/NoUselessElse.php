<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoUselessElseFixer (priority 39). An `else` (not `else if`, not the `else:` form) whose body is empty, or
 * whose preceding if / elseif blocks all end in `break` / `continue` / `exit` / `goto` / `return` / `throw`, is removed with its braces; the body stays.
 * A body that declares a named function or class stays an `else`. Upstream clears tokens with their whitespace merged, which as text is deleting the
 * token units; the walk (cleared tokens are invisible to every lookup) runs on a deleted-set over the Source.
 */
final class NoUselessElse extends EditRule
{
    /** @var array<int, bool> */
    private array $del = [];
    private Source $s;

    public function name(): string
    {
        return 'no_useless_else';
    }

    public function priority(): int
    {
        return 39;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ELSE) {
                return true;
            }
        }
        return false;
    }

    private function prev(int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!isset($this->del[$j])) {
                return $j;
            }
        }
        return -1;
    }

    private function next(int $i): int
    {
        $n = $this->s->n;
        for ($j = $i + 1; $j < $n; $j++) {
            if (!isset($this->del[$j])) {
                return $j;
            }
        }
        return $n;
    }

    private function pl(int $j, int $k): bool
    {
        return $j >= 0 && $j < $this->s->n && !isset($this->del[$j]) && $this->s->kind[$j] === $k && $this->s->ct[$j] === 0;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->del = [];
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            if (isset($this->del[$i]) || $s->kind[$i] !== \__McTokId::T_ELSE || $s->ct[$i] !== 0) {
                continue;
            }
            $nx = $this->next($i);
            if ($nx < $n && (($s->kind[$nx] === 58 && $s->ct[$nx] === 0) || $s->kind[$nx] === \__McTokId::T_IF)) {
                continue;
            }
            $this->fixEmptyElse($i);
            if (isset($this->del[$i])) {
                continue;
            }
            if ($this->isSuperfluousElse($i) && !$this->containsNamedSymbol($i)) {
                $this->clearElse($i);
            }
        }
        foreach ($this->del as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, '');
        }
        $this->del = [];
    }

    private function fixEmptyElse(int $i): void
    {
        $s = $this->s;
        $next = $this->next($i);
        if ($next >= $s->n) {
            return;
        }
        if ($this->pl($next, 123)) {
            $close = $s->pair[$next];
            if ($close > 0 && $this->next($next) === $close) {
                $this->clearElse($i);
            }
            return;
        }
        $end = $i + 1;
        while ($end < $s->n && !(($s->kind[$end] === 59 && $s->ct[$end] === 0) || $s->kind[$end] === \__McTokId::T_CLOSE_TAG)) {
            $end++;
        }
        if ($next === $end) {
            $this->clearElse($i);
        }
    }

    private function clearElse(int $i): void
    {
        $s = $this->s;
        $this->del[$i] = true;
        $next = $this->next($i);
        if (!$this->pl($next, 123)) {
            return;
        }
        $this->del[$s->pair[$next]] = true;
        $this->del[$next] = true;
    }

    private function containsNamedSymbol(int $i): bool
    {
        $s = $this->s;
        $next = $this->next($i);
        if (!$this->pl($next, 123)) {
            return false;
        }
        $close = $s->pair[$next];
        for ($j = $next + 1; $j < $close; $j++) {
            if (isset($this->del[$j]) || $s->ct[$j] !== 0) {
                continue;
            }
            $k = $s->kind[$j];
            if ($k === \__McTokId::T_FUNCTION && !Analyzer::isLambda($s, $j)) {
                return true;
            }
            if (($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) && !Analyzer::isAnonymousClass($s, $j)) {
                return true;
            }
        }
        return false;
    }

    /** the nearest token before $from that is plain-char $c (>= 0) or has kind $k in $kinds */
    private function prevOfKind(int $from, array $chars, array $kinds): int
    {
        $s = $this->s;
        for ($j = $from - 1; $j >= 0; $j--) {
            if (isset($this->del[$j])) {
                continue;
            }
            if ($s->ct[$j] !== 0) {
                continue;
            }
            $k = $s->kind[$j];
            if (isset($kinds[$k]) || ($k < 256 && isset($chars[$k]))) {
                return $j;
            }
        }
        return -1;
    }

    /** @return array{0:int,1:int} */
    private function previousBlock(int $index): array
    {
        $s = $this->s;
        $close = $this->prev($index);
        $previous = $close;
        if ($this->pl($close, 125)) {
            $previous = $s->pair[$close];
        }
        $open = $this->prevOfKind($previous, [], [\__McTokId::T_IF => 1, \__McTokId::T_ELSE => 1, \__McTokId::T_ELSEIF => 1]);
        if ($open >= 0 && $s->kind[$open] === \__McTokId::T_IF) {
            $e = $this->prev($open);
            if ($e >= 0 && $s->kind[$e] === \__McTokId::T_ELSE && $s->ct[$e] === 0) {
                $open = $e;
            }
        }
        return [$open, $close];
    }

    private function isSuperfluousElse(int $index): bool
    {
        $s = $this->s;
        $start = $index;
        do {
            [$start, $end] = $this->previousBlock($start);
            if ($start < 0 || $end < 0) {
                return false;
            }
            $previous = $end;
            if ($this->pl($previous, 125)) {
                $previous = $this->prev($previous);
            }
            if (!$this->pl($previous, 59)) {
                return false;
            }
            $pp = $this->prev($previous);
            if ($this->pl($pp, 123)) {
                return false;
            }
            $cand = $this->prevOfKind($previous, [59 => 1], [\__McTokId::T_BREAK => 1, \__McTokId::T_CLOSE_TAG => 1, \__McTokId::T_CONTINUE => 1, \__McTokId::T_EXIT => 1,
                \__McTokId::T_GOTO => 1, \__McTokId::T_IF => 1, \__McTokId::T_RETURN => 1, \__McTokId::T_THROW => 1]);
            if ($cand < 0) {
                return false;
            }
            $ck = $s->kind[$cand];
            if ($ck === 59 || $ck === \__McTokId::T_CLOSE_TAG || $ck === \__McTokId::T_IF) {
                return false;
            }
            if ($ck === \__McTokId::T_THROW) {
                $pi = $this->prev($cand);
                if (!($this->pl($pi, 59) || $this->pl($pi, 123))) {
                    return false;
                }
            }
            if ($this->inConditional($cand, $start) || $this->inConditionWithoutBraces($cand, $start)) {
                return false;
            }
        } while (!($s->kind[$start] === \__McTokId::T_IF && $s->ct[$start] === 0));
        return true;
    }

    private function inConditional(int $index, int $lower): bool
    {
        $s = $this->s;
        $c = $this->prevOfKind($index, [41 => 1, 59 => 1, 58 => 1], []);
        if ($c < 0) {
            return false;
        }
        if ($s->kind[$c] === 58) {
            return true;
        }
        if ($s->kind[$c] !== 41) {
            return false;
        }
        $open = $s->pair[$c];
        return $this->prev($open) > $lower;
    }

    private function inConditionWithoutBraces(int $index, int $lower): bool
    {
        $s = $this->s;
        do {
            if ($index < 0) {
                return false;
            }
            if (isset($this->del[$index])) {
                $index = $this->prev($index);
                if ($index < 0) {
                    return false;
                }
            }
            $k = $s->kind[$index];
            $plain = $s->ct[$index] === 0;
            if ($plain && ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_ELSE)) {
                return true;
            }
            if ($plain && $k === 59) {
                return false;
            }
            if ($plain && $k === 123) {
                $index = $this->prev($index);
                if ($index >= 0 && $s->kind[$index] === \__McTokId::T_DO && $s->ct[$index] === 0) {
                    $index--;
                    continue;
                }
                if (!$this->pl($index, 41)) {
                    return false;
                }
                $index = $s->pair[$index];
                $index = $this->prev($index);
                if ($index >= 0 && ($s->kind[$index] === \__McTokId::T_IF || $s->kind[$index] === \__McTokId::T_ELSEIF) && $s->ct[$index] === 0) {
                    return false;
                }
            } elseif ($plain && $k === 41) {
                $index = $s->pair[$index];
                $index = $this->prev($index);
            } else {
                $index--;
            }
        } while ($index > $lower);
        return false;
    }
}
