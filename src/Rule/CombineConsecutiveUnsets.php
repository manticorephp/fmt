<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\CombineConsecutiveUnsetsFixer (priority 24). `unset($a); unset($b);` -> `unset($a, $b);`: the tokens
 * inside the later call move in front of the earlier call's `)` (every other item, as upstream's stride-two copy), then the later `unset ( )` and its `;` go.
 * Whole-file FlatTokens port, from the last token to the first, with upstream's stale indexes.
 */
final class CombineConsecutiveUnsets extends FlatRule
{
    public function name(): string
    {
        return 'combine_consecutive_unsets';
    }

    public function priority(): int
    {
        return 24;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_UNSET) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index >= 0; --$index) {
            if (!self::is($t, $index, \__McTokId::T_UNSET)) {
                continue;
            }
            $prev = $this->previousUnsetCall($t, $index);
            if (\is_int($prev)) {
                $index = $prev;
                continue;
            }
            [$previousUnset, , $braceEnd] = $prev;
            $start = self::nextOf($t, $index, 40);
            if ($start < 0) {
                return;
            }
            $end = $t->blockEnd($start, 41);
            if ($end < 0) {
                return;
            }
            $added = $this->moveTokens($s, $t, $start, $end, $braceEnd - 1);
            if ($s->abort) {
                return;
            }
            if (!$t->isWs($braceEnd)) {
                $t->insertAt($braceEnd, [FlatTokens::ws(' ')]);
                ++$added;
            }
            $before = $t->meaningful($braceEnd, -1);
            if ($before < 0 || !self::is($t, $before, 44)) {
                $t->insertAt($braceEnd, [FlatTokens::code(44, ',')]);
                ++$added;
            } elseif ($t->isWs($before + 1)) {
                self::clearMerge($t, $before + 1);
            }
            foreach ([$index, $start, $end] as $q) {
                self::clearMerge($t, $q + $added);
            }
            $semi = $t->meaningful($end, 1);
            if ($semi >= 0 && self::is($t, $semi, 59)) {
                self::clearMerge($t, $semi);
            }
            $index = $previousUnset + 1;
        }
    }

    /** @return int|array{0:int,1:int,2:int,3:int} */
    private function previousUnsetCall(FlatTokens $t, int $index)
    {
        $semi = $t->meaningful($index, -1);
        if ($semi < 0) {
            return $index;
        }
        if (!self::is($t, $semi, 59)) {
            return $semi;
        }
        $end = $t->meaningful($semi, -1);
        if ($end < 0) {
            return $index;
        }
        if (!self::is($t, $end, 41)) {
            return $end;
        }
        $start = $t->blockStart($end, 40);
        $unset = $start < 0 ? -1 : $t->meaningful($start, -1);
        if ($unset < 0) {
            return $index;
        }
        if (!self::is($t, $unset, \__McTokId::T_UNSET)) {
            return $unset;
        }
        return [$unset, $start, $end, $semi];
    }

    private function moveTokens(Source $s, FlatTokens $t, int $start, int $end, int $to): int
    {
        $added = 0;
        for ($i = $start + 1; $i < $end; $i += 2) {
            if ($t->isWs($i) && $t->isWs($to + 1)) {
                $t->c[$to + 1] = $t->c[$to + 1] . $t->c[$i];
            } else {
                if ($t->isEmpty($i)) {
                    $s->abort = true; // Tokens::insertAt throws "Must not add empty token": the file stays as it is
                    return $added;
                }
                if ($t->i[$i] === -1) {
                    $item = [$t->k[$i], $t->c[$i], -1];
                } else {
                    $item = FlatTokens::code($t->k[$i], $t->content($i));
                }
                $t->insertAt(++$to, [$item]);
                ++$end;
                ++$added;
            }
            $t->clearAt($i + 1);
        }
        return $added;
    }
}
