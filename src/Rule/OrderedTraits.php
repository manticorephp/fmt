<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\OrderedTraitsFixer (priority 0, risky). The trait names of every `use A, B;` statement, and the consecutive `use` statements of a
 * class body, are sorted alphabetically (case-insensitive unless case_sensitive); a statement travels with the comments before it. Whole-file FlatTokens port: the statement slices
 * are copied into temporary FlatTokens (upstream's `Tokens::fromArray`), sorted there and written back with overrideRange, last slot first.
 */
final class OrderedTraits extends FlatRule
{
    public function __construct(private bool $caseSensitive)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'ordered_traits';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->ct[$i] === CT::USE_TRAIT) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $max = $t->count();
        /** @var int[] $us statement starts / ends of the group being collected (see tests/repro/ownflow_reset_array_of_int_lists.php) */
        $us = [];
        /** @var int[] $ue */
        $ue = [];
        for ($index = 1; $index < $max; $index++) {
            $k = $t->k[$index];
            if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT) {
                continue;
            }
            if (!($t->i[$index] !== -1 && $k === CT::USE_TRAIT)) {
                if (\count($us) > 0) {
                    $this->sortGroup($t, $us, $ue);
                    $us = [];
                    $ue = [];
                }
                continue;
            }
            $prev = $t->meaningful($index, -1);
            $start = $t->nonWhitespace($prev, 1);
            $end = -1;
            for ($j = $index + 1; $j < $max; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 123)) {
                    $end = $j;
                    break;
                }
            }
            if ($end < 0) {
                return;
            }
            if ($t->k[$end] === 123) {
                $end = $t->blockEnd($end, 125);
            }
            $us[] = $start;
            $ue[] = $end;
            $index = $end;
        }
    }

    /**
     * @param int[] $us
     * @param int[] $ue
     */
    private function sortGroup(FlatTokens $t, array $us, array $ue): void
    {
        /** @var array<int, FlatTokens> $slices */
        $slices = [];
        foreach ($us as $x => $start) {
            $slices[$start] = self::slice($t, $start, $ue[$x]);
        }
        foreach ($slices as $start => $slice) {
            $this->sortMultiple($slice);
        }
        $this->sort($t, $slices);
    }

    private static function slice(FlatTokens $t, int $a, int $b): FlatTokens
    {
        $o = new FlatTokens();
        $k = [];
        $c = [];
        $i = [];
        for ($q = $a; $q <= $b; $q++) {
            $k[] = $t->k[$q];
            $c[] = $t->content($q);
            $i[] = $t->i[$q] === -1 ? -1 : FlatTokens::NEW;
        }
        $o->k = $k;
        $o->c = $c;
        $o->i = $i;
        return $o;
    }

    private function sortMultiple(FlatTokens $use): void
    {
        /** @var array<int, FlatTokens> $traits */
        $traits = [];
        $indexOfName = -1;
        $nk = [];
        $nc = [];
        $ni = [];
        $max = $use->count();
        for ($index = 0; $index < $max; $index++) {
            $k = $use->k[$index];
            if ($use->i[$index] !== -1 && ($k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR)) {
                $nk[] = $k;
                $nc[] = $use->c[$index];
                $ni[] = FlatTokens::NEW;
                if ($indexOfName < 0) {
                    $indexOfName = $index;
                }
                continue;
            }
            if ($use->i[$index] !== -1 && ($k === 44 || $k === 59 || $k === 123)) {
                $e = new FlatTokens();
                $e->k = $nk;
                $e->c = $nc;
                $e->i = $ni;
                $traits[$indexOfName] = $e;
                $nk = [];
                $nc = [];
                $ni = [];
                $indexOfName = -1;
            }
            if ($use->i[$index] !== -1 && $k === 123) {
                $index = $use->blockEnd($index, 125);
            }
        }
        $this->sort($use, $traits);
    }

    private function traitName(FlatTokens $u): string
    {
        $s = '';
        $m = $u->count();
        for ($q = 0; $q < $m; $q++) {
            if ($u->i[$q] === -1) {
                continue;
            }
            $k = $u->k[$q];
            if ($k === 59 || $k === 123) {
                break;
            }
            if ($k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STRING) {
                $s .= $u->c[$q];
            }
        }
        return \ltrim($s, '\\');
    }

    /** @param array<int, FlatTokens> $elements */
    private function sort(FlatTokens $target, array $elements): void
    {
        $keys = \array_keys($elements);
        $n = \count($keys);
        $names = [];
        foreach ($keys as $x => $key) {
            $names[$x] = $this->traitName($elements[$key]);
        }
        // stable insertion sort of the positions (uasort keeps equal elements in order)
        $order = [];
        for ($x = 0; $x < $n; $x++) {
            $order[] = $x;
        }
        for ($x = 1; $x < $n; $x++) {
            $v = $order[$x];
            $y = $x - 1;
            while ($y >= 0 && $this->cmp($names[$order[$y]], $names[$v]) > 0) {
                $order[$y + 1] = $order[$y];
                $y--;
            }
            $order[$y + 1] = $v;
        }
        $before = $target->count();
        for ($x = $n - 1; $x >= 0; $x--) {
            $key = $keys[$x];
            $src = $elements[$keys[$order[$x]]];
            $items = [];
            $m = $src->count();
            for ($q = 0; $q < $m; $q++) {
                $items[] = [$src->k[$q], $src->c[$q], $src->i[$q]];
            }
            self::overrideRange($target, $key, $key + $elements[$key]->count() - 1, $items);
        }
        if ($before < $target->count()) {
            self::clearEmpty($target);
        }
    }

    private function cmp(string $a, string $b): int
    {
        if ($this->caseSensitive) {
            return $a <=> $b;
        }
        return \strcasecmp($a, $b);
    }
}
