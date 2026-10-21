<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Strict\StrictParamFixer (priority 31, risky). A global call of array_keys / array_search / in_array (no 3rd argument), base64_decode (no 2nd) or
 * mb_detect_encoding (no 3rd; a missing 2nd becomes `mb_detect_order()`) gets the `true` strict argument. Whole-file FlatTokens port, last call first.
 */
final class StrictParam extends FlatRule
{
    public function name(): string
    {
        return 'strict_param';
    }

    public function priority(): int
    {
        return 31;
    }

    /** a call of one of the functions with fewer top-level arguments than the strict one needs (the global-call test is left to the real pass) */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i + 2 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->kind[$i + 1] !== 40) {
                continue;
            }
            $len = $s->end[$i] - $s->start[$i];
            if ($len < 8 || $len > 18) {
                continue;
            }
            $params = self::params(\strtolower($s->text($i)));
            if ($params === null) {
                continue;
            }
            $close = $s->pair[$i + 1];
            $args = $close === $i + 2 ? 0 : 1;
            for ($j = $i + 2; $j < $close; $j++) {
                $k = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if (\Fmt\TypeWalk::closer($k) >= 0 && $s->pair[$j] > $j) {
                    $j = $s->pair[$j];
                    continue;
                }
                if ($k === 44 && $j + 1 < $close) {
                    $args++;
                }
            }
            if ($args < \count($params)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int, string>|null per position: '' = no default (stop), 'true', or 'order' (mb_detect_order()) */
    private static function params(string $lower): ?array
    {
        return match ($lower) {
            'array_keys', 'array_search', 'in_array' => ['', '', 'true'],
            'base64_decode' => ['', 'true'],
            'mb_detect_encoding' => ['', 'order', 'true'],
            default => null,
        };
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING) {
                continue;
            }
            $params = self::params(\strtolower($t->content($index)));
            if ($params === null || !$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $this->fix($t, $index, $params);
        }
    }

    /** @param array<int, string> $params */
    private function fix(FlatTokens $t, int $fn, array $params): void
    {
        $open = self::nextOf($t, $fn, 40);
        $close = $t->blockEnd($open, 41);
        $quantity = 0;
        $expect = true;
        for ($index = $open + 1; $index < $close; $index++) {
            $k = $t->k[$index];
            if ($expect && $k !== FlatTokens::WS && $k !== FlatTokens::COMMENT) {
                $quantity++;
                $expect = false;
            }
            if ($t->i[$index] !== -1 && $k === 40) {
                $index = $t->blockEnd($index, 41);
                continue;
            }
            if ($t->i[$index] !== -1 && $k === CT::ARRAY_BRACKET_OPEN) {
                $index = $t->blockEnd($index, CT::ARRAY_BRACKET_CLOSE);
                continue;
            }
            if ($t->i[$index] !== -1 && $k === 44) {
                $expect = true;
                continue;
            }
        }
        $want = \count($params);
        if ($quantity === $want) {
            return;
        }
        $items = [];
        for ($i = $quantity; $i < $want; $i++) {
            if ($params[$i] === '') {
                return;
            }
            $items[] = FlatTokens::code(44, ',');
            $items[] = FlatTokens::ws(' ');
            if ($params[$i] === 'true') {
                $items[] = FlatTokens::code(\__McTokId::T_STRING, 'true');
                continue;
            }
            $items[] = FlatTokens::code(\__McTokId::T_STRING, 'mb_detect_order');
            $items[] = FlatTokens::code(40, '(');
            $items[] = FlatTokens::code(41, ')');
        }
        $before = $t->meaningful($close, -1);
        if (self::is($t, $before, 44)) {
            \array_shift($items);
            $items[] = FlatTokens::code(44, ',');
        }
        $t->insertAt($before + 1, $items);
    }
}
