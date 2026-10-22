<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\TernaryToNullCoalescingFixer (priority 0). `isset($a) ? $a : $b` -> `$a ?? $b` when the isset has one operand
 * that is the ternary's first operand (compared as the code of the meaningful tokens), the isset has no `++` `--` `yield` `(`, is not `$this`, and the token
 * before is no operator of higher precedence. The comments between `isset` and the operand survive. Whole-file FlatTokens port, last isset first.
 */
final class TernaryToNullCoalescing extends FlatRule
{
    public function name(): string
    {
        return 'ternary_to_null_coalescing';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ISSET) {
                $o = $i + 1;
                if ($o < $s->n && $s->kind[$o] === 40 && $s->pair[$o] >= 0 && $s->pair[$o] + 1 < $s->n && $s->kind[$s->pair[$o] + 1] === 63) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $at = [];
        $m = $t->count();
        for ($q = 0; $q < $m; $q++) {
            if (self::is($t, $q, \__McTokId::T_ISSET)) {
                $at[] = $q;
            }
        }
        for ($x = \count($at) - 1; $x >= 0; $x--) {
            $this->fixIsset($t, $at[$x]);
        }
    }

    private function fixIsset(FlatTokens $t, int $index): void
    {
        $prev = $t->meaningful($index, -1);
        if ($prev >= 0 && $this->higherPrecedence($t, $prev)) {
            return;
        }
        $start = self::nextOf($t, $index, 40);
        if ($start < 0) {
            return;
        }
        $end = $t->blockEnd($start, 41);
        $question = $t->meaningful($end, 1);
        if (!self::is($t, $question, 63)) {
            return;
        }
        if ($this->changing($t, $start, $end)) {
            return;
        }
        $issetCode = $this->sequenceCode($t, $start, $end);
        if ($issetCode === '$this') {
            return;
        }
        $colon = self::nextOf($t, $question, 58);
        if ($colon < 0) {
            return;
        }
        if ($issetCode !== $this->sequenceCode($t, $question, $colon)) {
            return;
        }
        $first = $t->meaningful($question, 1);
        $comments = [];
        $started = false;
        for ($j = $index; $j < $first; ++$j) {
            if ($t->k[$j] === FlatTokens::COMMENT) {
                $comments[] = $t->item($j);
                $started = true;
            } elseif ($started) {
                if ($t->k[$j] === FlatTokens::WS) {
                    $comments[] = $t->item($j);
                }
                $started = false;
            }
        }
        self::setCode($t, $colon, \__McTokId::T_COALESCE, '??');
        self::overrideRange($t, $index, $first - 1, $comments);
    }

    /** the concatenated content of the meaningful tokens strictly between $start and $end */
    private function sequenceCode(FlatTokens $t, int $start, int $end): string
    {
        $code = '';
        $index = $start;
        while ($index < $end) {
            $index = $t->meaningful($index, 1);
            if ($index < 0 || $index >= $end) {
                break;
            }
            $code .= $t->content($index);
        }
        return $code;
    }

    private function changing(FlatTokens $t, int $start, int $end): bool
    {
        $index = $start;
        while ($index < $end) {
            $index = $t->meaningful($index, 1);
            if ($index < 0 || $index >= $end) {
                break;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_DEC || $k === \__McTokId::T_INC || $k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM || $k === 40) {
                return true;
            }
        }
        return false;
    }

    private function higherPrecedence(FlatTokens $t, int $q): bool
    {
        $k = $t->k[$q];
        if (\in_array($k, [\__McTokId::T_ARRAY_CAST, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR, \__McTokId::T_BOOL_CAST, \__McTokId::T_COALESCE, \__McTokId::T_DEC,
            \__McTokId::T_DOUBLE_CAST, \__McTokId::T_INC, \__McTokId::T_INT_CAST, \__McTokId::T_IS_EQUAL, \__McTokId::T_IS_GREATER_OR_EQUAL, \__McTokId::T_IS_IDENTICAL,
            \__McTokId::T_IS_NOT_EQUAL, \__McTokId::T_IS_NOT_IDENTICAL, \__McTokId::T_IS_SMALLER_OR_EQUAL, \__McTokId::T_OBJECT_CAST, \__McTokId::T_POW, \__McTokId::T_SL,
            \__McTokId::T_SPACESHIP, \__McTokId::T_SR, \__McTokId::T_STRING_CAST, \__McTokId::T_UNSET_CAST], true)) {
            return true;
        }
        if (\in_array($k, [33, 37, 42, 43, 45, 47, 58, 94, 124, 126, 46], true)) {
            return true;
        }
        return $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }
}
