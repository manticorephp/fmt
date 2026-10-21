<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\CombineConsecutiveIssetsFixer (priority 4). `isset($a) && isset($b)` -> `isset($a, $b)`: the first isset of a
 * `&&` chain swallows the meaningful tokens of the next one (comma + space + the tokens between its parentheses), whose tokens are cleared with their
 * whitespace merged. Whole-file FlatTokens port (upstream's integer indexes, stale ones included).
 */
final class CombineConsecutiveIssets extends FlatRule
{
    public function name(): string
    {
        return 'combine_consecutive_issets';
    }

    public function priority(): int
    {
        return 4;
    }

    protected function candidate(Source $s): bool
    {
        // a change needs `isset(...) && isset` (meaningful tokens)
        for ($i = 0; $i + 1 < $s->n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_ISSET || $s->kind[$i + 1] !== 40) {
                continue;
            }
            $p = $s->pair[$i + 1];
            if ($p > 0 && $p + 2 < $s->n && $s->kind[$p + 1] === \__McTokId::T_BOOLEAN_AND && $s->kind[$p + 2] === \__McTokId::T_ISSET) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $count = $t->count();
        for ($index = 1; $index < $count; ++$index) {
            if (!self::is($t, $index, \__McTokId::T_ISSET)) {
                continue;
            }
            $pm = $t->meaningful($index, -1);
            if ($pm < 0 || !self::isAny($t, $pm, [40, 123, 59, 61, \__McTokId::T_OPEN_TAG, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR])) {
                continue;
            }
            $info = $this->info($t, $index);
            $nInfo = \count($info);
            $closeBrace = $info[$nInfo - 1];
            $insertLocation = $info[$nInfo - 2] + 1;
            $and = $t->meaningful($closeBrace, 1);
            while ($and >= 0 && self::is($t, $and, \__McTokId::T_BOOLEAN_AND)) {
                $issetIndex = $t->meaningful($and, 1);
                if ($issetIndex < 0) {
                    return;
                }
                if (!self::is($t, $issetIndex, \__McTokId::T_ISSET)) {
                    $index = $issetIndex;
                    break;
                }
                $next = $this->info($t, $issetIndex);
                $nm = $t->meaningful($next[\count($next) - 1], 1);
                if ($nm < 0) {
                    return;
                }
                if (!self::isAny($t, $nm, [41, 125, 59, \__McTokId::T_CLOSE_TAG, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR])) {
                    $index = $nm;
                    break;
                }
                $items = [FlatTokens::code(44, ','), FlatTokens::ws(' ')];
                for ($x = 1; $x < \count($next) - 1; $x++) {
                    $items[] = FlatTokens::code($t->k[$next[$x]], $t->content($next[$x]));
                }
                foreach ($next as $q) {
                    self::clearMerge($t, $q);
                }
                self::clearMerge($t, $issetIndex);
                self::clearMerge($t, $and);
                $t->insertAt($insertLocation, $items);
                $inserted = \count($items);
                $count += $inserted;
                $closeBrace += $inserted;
                $insertLocation += $inserted;
                $and = $t->meaningful($closeBrace, 1);
            }
        }
    }

    /**
     * @param int[] $ids
     */
    private static function isAny(FlatTokens $t, int $q, array $ids): bool
    {
        return $t->i[$q] !== -1 && \in_array($t->k[$q], $ids, true);
    }

    /** @return int[] the meaningful tokens of the isset at $index: `(` ... the matching `)` */
    private function info(FlatTokens $t, int $index): array
    {
        $open = $t->meaningful($index, 1);
        $depth = 1;
        $out = [$open];
        $m = $t->count();
        for ($i = $open + 1; $i < $m; ++$i) {
            if ($t->i[$i] === -1) {
                continue;
            }
            $out[] = $i;
            if ($t->k[$i] === 41) {
                --$depth;
                if ($depth === 0) {
                    break;
                }
            } elseif ($t->k[$i] === 40) {
                ++$depth;
            }
        }
        return $out;
    }
}
