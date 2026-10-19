<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\NoUselessConcatOperatorFixer (priority 5), juggle_simple_strings=false. `'a' . 'b'` -> `'ab'`, `"a" . "b"` -> `"ab"`,
 * `"a $x" . "b"` -> `"a {$x}b"`-style merges of interpolated strings (refused when the first ends in a variable the second would extend). Operands on
 * different lines are left. Ported on a whole-file FlatTokens, last `.` first: a merged operand is the operand of the `.` before it.
 */
final class NoUselessConcatOperator extends FlatRule
{
    private const DQ = 0;
    private const DQV = 1;
    private const SQ = 2;

    public function name(): string
    {
        return 'no_useless_concat_operator';
    }

    public function priority(): int
    {
        return 5;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i + 1 < $s->n; $i++) {
            if ($s->kind[$i] !== 46 || $s->ct[$i] !== 0) {
                continue;
            }
            $p = $s->kind[$i - 1];
            $n = $s->kind[$i + 1];
            if (($p === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $p === 34) && ($n === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $n === 34)) {
                return true;
            }
        }
        return false;
    }

    private static function lineBreak(FlatTokens $t, int $start, int $end): bool
    {
        for ($i = $end; $i > $start; $i--) {
            $c = $t->i[$i] !== -1 ? $t->content($i) : $t->c[$i];
            if ($c !== '' && Trivia::pmatch('/\R/', $c)) {
                return true;
            }
        }
        return false;
    }

    /** the prelude tokenizer splits a binary flag off (`b'x'` -> T_STRING `b` + string; compiler issue, see NoBinaryString): such an operand is upstream's null */
    private static function flagged(FlatTokens $t, int $start): bool
    {
        $p = $start - 1;
        return $p >= 0 && $t->i[$p] !== -1 && $t->k[$p] === \__McTokId::T_STRING && \strlen($t->content($p)) === 1 && ($t->content($p) === 'b' || $t->content($p) === 'B');
    }

    /** @return array{0:int,1:int,2:int}|null [start, end, type] */
    private static function operand(FlatTokens $t, int $q, int $dir): ?array
    {
        $r = self::operandRaw($t, $q, $dir);
        return $r !== null && !self::flagged($t, $r[0]) ? $r : null;
    }

    /** @return array{0:int,1:int,2:int}|null */
    private static function operandRaw(FlatTokens $t, int $q, int $dir): ?array
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return null;
        }
        if ($t->k[$q] === \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
            $c = $t->content($q);
            if ($c[0] === 'b' || $c[0] === 'B') {
                return null;
            }
            return [$q, $q, $c[0] === '"' ? self::DQ : self::SQ];
        }
        if ($t->k[$q] === 34) {
            $m = $t->count();
            $e = -1;
            for ($j = $q + $dir; $j >= 0 && $j < $m; $j += $dir) {
                if ($t->i[$j] !== -1 && $t->k[$j] === 34) {
                    $e = $j;
                    break;
                }
            }
            if ($e < 0) {
                return null;
            }
            return $dir === 1 ? [$q, $e, self::DQV] : [$e, $q, self::DQV];
        }
        return null;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if ($t->i[$q] === -1 || $t->k[$q] !== 46) {
                continue;
            }
            $nx = $t->meaningful($q, 1);
            if ($nx < 0 || self::lineBreak($t, $q, $nx)) {
                continue;
            }
            $second = self::operand($t, $nx, 1);
            if ($second === null) {
                continue;
            }
            $pv = $t->meaningful($q, -1);
            if ($pv < 0 || self::lineBreak($t, $pv, $q)) {
                continue;
            }
            $first = self::operand($t, $pv, -1);
            if ($first === null) {
                continue;
            }
            $this->fix($t, $first, $q, $second);
        }
    }

    /**
     * @param array{0:int,1:int,2:int} $first
     * @param array{0:int,1:int,2:int} $second
     */
    private function fix(FlatTokens $t, array $first, int $q, array $second): void
    {
        $ft = $first[2];
        $st = $second[2];
        if (($ft === self::DQ && $st === self::DQ) || ($ft === self::SQ && $st === self::SQ)) {
            $this->mergeConstant($t, $first, $q, $second);
            return;
        }
        if ($ft === self::DQV && $st === self::DQV) {
            if ($this->cannotMerge($t, $first, $second)) {
                return;
            }
            $this->mergeVar($t, $first, $q, $second);
            return;
        }
        foreach ([[$first, $second], [$second, $first]] as [$o1, $o2]) {
            if ($o1[2] === self::DQV && $o2[2] === self::DQ) {
                if ($this->cannotMerge($t, $o1, $o2)) {
                    return;
                }
                $this->mergeVar($t, $first, $q, $second);
                return;
            }
        }
    }

    /**
     * @param array{0:int,1:int,2:int} $first
     * @param array{0:int,1:int,2:int} $second
     */
    private function mergeConstant(FlatTokens $t, array $first, int $q, array $second): void
    {
        $quote = $first[2] === self::DQ || $second[2] === self::DQ ? '"' : "'";
        $a = $t->content($first[0]);
        $b = $t->content($second[0]);
        $t->i[$first[0]] = FlatTokens::NEW;
        $t->c[$first[0]] = $quote . \substr($a, 1, -1) . \substr($b, 1, -1) . $quote;
        $this->clearConcatAndAround($t, $q);
        self::clearMerge($t, $second[0]);
    }

    private function clearConcatAndAround(FlatTokens $t, int $q): void
    {
        if ($t->isWs($q + 1)) {
            self::clearMerge($t, $q + 1);
        }
        self::clearMerge($t, $q);
        if ($t->isWs($q - 1)) {
            self::clearMerge($t, $q - 1);
        }
    }

    /**
     * @param array{0:int,1:int,2:int} $first
     * @param array{0:int,1:int,2:int} $second
     */
    private function cannotMerge(FlatTokens $t, array $first, array $second): bool
    {
        if ($first[2] !== self::DQV) {
            return false;
        }
        $e = $first[1] - 1;
        if (!($e >= 0 && $t->i[$e] !== -1 && $t->k[$e] === \__McTokId::T_VARIABLE)) {
            return false;
        }
        $code = '';
        for ($i = $second[0]; $i <= $second[1]; $i++) {
            $code .= $t->i[$i] !== -1 ? $t->content($i) : $t->c[$i];
        }
        $inner = \substr($code, 1, -1);
        if (Trivia::pmatch('/^ .*/', $inner) || Trivia::pmatch('/^-(?!\>)/', $inner)) {
            return false;
        }
        return true;
    }

    /**
     * @param array{0:int,1:int,2:int} $first
     * @param array{0:int,1:int,2:int} $second
     */
    private function mergeVar(FlatTokens $t, array $first, int $q, array $second): void
    {
        $new = '';
        foreach ([$first, $second] as $op) {
            $content = '';
            for ($i = $op[0]; $i <= $op[1];) {
                $content .= $t->content($i);
                $i = $t->meaningful($i, 1);
                if ($i < 0) {
                    break;
                }
            }
            $new .= \substr($content, 1, -1);
        }
        for ($i = $second[1]; $i >= $second[0];) {
            self::clearMerge($t, $i);
            $i = $t->meaningful($i, -1);
            if ($i < 0) {
                break;
            }
        }
        $this->clearConcatAndAround($t, $q);
        for ($i = $first[1]; $i > $first[0];) {
            self::clearMerge($t, $i);
            $i = $t->meaningful($i, -1);
            if ($i < 0) {
                break;
            }
        }
        $p = Source::parse('<?php "' . $new . '";');
        $items = [];
        for ($k = 1; $k < $p->n - 1; $k++) {
            if ($k > 1 && $p->gapBefore[$k] !== '') {
                foreach (Trivia::pieces($p->gapBefore[$k]) as $piece) {
                    $items[] = Gap::isSpace($piece) ? FlatTokens::ws($piece) : [FlatTokens::COMMENT, $piece, -1];
                }
            }
            $items[] = FlatTokens::code($p->ct[$k] !== 0 ? $p->ct[$k] : $p->kind[$k], $p->text($k));
        }
        (new TypeWalk($t))->overrideRange($first[0], $first[0], $items);
    }
}
