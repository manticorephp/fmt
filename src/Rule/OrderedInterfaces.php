<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\OrderedInterfacesFixer (priority 0). The interface list of `implements` (and of `interface X extends`) is sorted
 * (alpha: case-insensitive unless case_sensitive; length) ascending / descending; each interface travels with the trivia around its name. The sort is stable.
 * Whole-file FlatTokens port.
 */
final class OrderedInterfaces extends FlatRule
{
    public function __construct(private bool $length, private bool $descend, private bool $caseSensitive)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'ordered_interfaces';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        $iface = false;
        $ext = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_IMPLEMENTS) {
                return true;
            }
            $iface = $iface || $k === \__McTokId::T_INTERFACE;
            $ext = $ext || $k === \__McTokId::T_EXTENDS;
        }
        return $iface && $ext;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $m = $t->count();
        for ($index = 0; $index < $m; ++$index) {
            if (!self::is($t, $index, \__McTokId::T_IMPLEMENTS)) {
                if (!self::is($t, $index, \__McTokId::T_EXTENDS)) {
                    continue;
                }
                $name = $t->meaningful($index, -1);
                $kw = $t->meaningful($name, -1);
                if (!self::is($t, $kw, \__McTokId::T_INTERFACE)) {
                    continue;
                }
            }
            $start = $index + 1;
            $open = self::nextOf($t, $start, 123);
            if ($open < 0) {
                return;
            }
            $end = $t->meaningful($open, -1);
            /** @var int[][] $groups item indexes of each interface (a comma splits) */
            $groups = [[]];
            for ($i = $start; $i <= $end; ++$i) {
                if (self::is($t, $i, 44)) {
                    $groups[] = [];
                    continue;
                }
                $groups[\count($groups) - 1][] = $i;
            }
            if (\count($groups) === 1) {
                continue;
            }
            /** @var string[] $norm */
            $norm = [];
            foreach ($groups as $g) {
                $normalized = '';
                $first = -1;
                foreach ($g as $q) {
                    if ($t->i[$q] !== -1) {
                        $first = $q;
                        break;
                    }
                }
                if ($first >= 0) {
                    for ($q = $first; $q <= $g[\count($g) - 1]; ++$q) {
                        if ($t->k[$q] === FlatTokens::WS || $t->k[$q] === FlatTokens::COMMENT) {
                            break;
                        }
                        $normalized .= \str_replace('\\', ' ', $t->content($q));
                    }
                }
                $norm[] = $normalized;
            }
            // stable sort of the group numbers
            /** @var int[] $order */
            $order = [];
            foreach ($groups as $gi => $_) {
                $pos = \count($order);
                while ($pos > 0 && $this->score($norm[$order[$pos - 1]], $norm[$gi]) > 0) {
                    $pos--;
                }
                $next = [];
                for ($x = 0; $x < $pos; $x++) {
                    $next[] = $order[$x];
                }
                $next[] = $gi;
                for ($x = $pos; $x < \count($order); $x++) {
                    $next[] = $order[$x];
                }
                $order = $next;
            }
            $changed = false;
            foreach ($order as $pos => $gi) {
                if ($pos !== $gi) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                continue;
            }
            $items = [];
            foreach ($order as $pos => $gi) {
                if ($pos > 0) {
                    $items[] = FlatTokens::code(44, ',');
                }
                foreach ($groups[$gi] as $q) {
                    $items[] = $t->item($q);
                }
            }
            if ($items[0][2] !== -1) {
                // the first interface lost its leading whitespace: `implements\Foo` / `implementsFoo` lex as one name, upstream's result does not lint and the file stays as it is
                $s->abort = true;
                return;
            }
            self::overrideRange($t, $start, $end, $items);
        }
    }

    private function score(string $first, string $second): int
    {
        if ($this->length) {
            $score = \strlen($first) - \strlen($second);
        } elseif ($this->caseSensitive) {
            $score = $first <=> $second;
        } else {
            $score = \strcasecmp($first, $second);
        }
        return $this->descend ? $score * -1 : $score;
    }
}
