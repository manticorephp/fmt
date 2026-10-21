<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\ModernizeStrposFixer (priority 37, risky). `strpos($h, $n) === 0` -> `str_starts_with($h, $n)`, `strpos(..) !== false` -> `str_contains(..)`
 * and the negations; with modernize_stripos `stripos` too (both arguments wrapped in `strtolower`, with upstream's index arithmetic). Whole-file FlatTokens port, last token first.
 */
final class ModernizeStrpos extends FlatRule
{
    public function __construct(private bool $stripos)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'modernize_strpos';
    }

    public function priority(): int
    {
        return 37;
    }

    protected function candidate(Source $s): bool
    {
        $names = $this->stripos ? ['strpos', 'stripos'] : ['strpos'];
        if (!self::hasCallOf($s, $names)) {
            return false;
        }
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_IS_IDENTICAL || $s->kind[$i] === \__McTokId::T_IS_NOT_IDENTICAL) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index > 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING) {
                continue;
            }
            $lower = \strtolower($t->content($index));
            if (!($lower === 'strpos' || ($this->stripos && $lower === 'stripos')) || !$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $open = $t->meaningful($index, 1);
            $close = $t->blockEnd($open, 41);
            if (\count(self::callArguments($t, $open, $close)) !== 2) {
                continue;
            }
            $cmp = $this->compareTokens($t, $index, -1);
            if ($cmp === null) {
                $cmp = $this->compareTokens($t, $close, 1);
            }
            if ($cmp !== null) {
                $this->fixCall($t, $index, $cmp, $lower === 'stripos');
            }
        }
    }

    /** @param int[] $cmp [operator index, operand index] */
    private function fixCall(FlatTokens $t, int $fn, array $cmp, bool $ci): void
    {
        $opK = $t->k[$cmp[0]];
        $operand = $t->content($cmp[1]);
        $isZero = $t->k[$cmp[1]] === \__McTokId::T_LNUMBER && $operand === '0';
        $isFalse = $t->k[$cmp[1]] === \__McTokId::T_STRING && \strtolower($operand) === 'false';
        $negate = false;
        $name = '';
        if ($opK === \__McTokId::T_IS_IDENTICAL && $isZero) {
            $name = 'str_starts_with';
        } elseif ($opK === \__McTokId::T_IS_NOT_IDENTICAL && $isZero) {
            $name = 'str_starts_with';
            $negate = true;
        } elseif ($opK === \__McTokId::T_IS_NOT_IDENTICAL && $isFalse) {
            $name = 'str_contains';
        } elseif ($opK === \__McTokId::T_IS_IDENTICAL && $isFalse) {
            $name = 'str_contains';
            $negate = true;
        } else {
            return;
        }
        self::clearMerge($t, $cmp[0]);
        self::clearMerge($t, $cmp[1]);
        self::clearMerge($t, $fn);
        if ($negate) {
            $at = $fn;
            $prev = $t->meaningful($fn, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                $at = $prev;
            }
            $t->insertAt($at, [FlatTokens::code(33, '!')]);
            $fn++;
        }
        $t->insertAt($fn, [FlatTokens::code(\__McTokId::T_STRING, $name)]);
        if ($ci) {
            $this->wrap($t, $fn);
        }
    }

    private function wrap(FlatTokens $t, int $fn): void
    {
        $ns = $fn - 1 >= 0 && $t->i[$fn - 1] !== -1 && $t->k[$fn - 1] === \__McTokId::T_NS_SEPARATOR;
        $nsn = $ns ? 1 : 0;
        $open = $t->meaningful($fn, 1);
        $close = $t->blockEnd($open, 41);
        $args = self::callArguments($t, $open, $close);
        $starts = \array_keys($args);
        $firstStart = $starts[0];
        $lastKey = $starts[\count($starts) - 1];
        $firstEnd = $args[$firstStart] + 3 + $nsn;
        $secondWs = $t->k[$lastKey] === FlatTokens::WS && $t->c[$lastKey] !== '';
        if ($secondWs) {
            $secondStart = $t->meaningful($lastKey, 1);
        } else {
            $secondStart = $lastKey;
        }
        $secondStart += 3 + $nsn;
        $secondEnd = $args[$lastKey] + 6 + $nsn + ($secondWs ? 1 : 0);
        if ($ns) {
            $t->insertAt($firstStart, [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\')]);
            $firstStart++;
        }
        $t->insertAt($firstStart, [FlatTokens::code(\__McTokId::T_STRING, 'strtolower'), FlatTokens::code(40, '(')]);
        $t->insertAt($firstEnd, [FlatTokens::code(41, ')')]);
        if ($ns) {
            $t->insertAt($secondStart, [FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\')]);
            $secondStart++;
        }
        $t->insertAt($secondStart, [FlatTokens::code(\__McTokId::T_STRING, 'strtolower'), FlatTokens::code(40, '(')]);
        $t->insertAt($secondEnd, [FlatTokens::code(41, ')')]);
    }

    /** @return int[]|null */
    private function compareTokens(FlatTokens $t, int $offset, int $dir): ?array
    {
        $op = $t->meaningful($offset, $dir);
        if ($op >= 0 && self::is($t, $op, \__McTokId::T_NS_SEPARATOR)) {
            $op = $t->meaningful($op, $dir);
        }
        if ($op < 0 || !(self::is($t, $op, \__McTokId::T_IS_IDENTICAL) || self::is($t, $op, \__McTokId::T_IS_NOT_IDENTICAL))) {
            return null;
        }
        $operand = $t->meaningful($op, $dir);
        if ($operand < 0 || $t->i[$operand] === -1) {
            return null;
        }
        $isZero = $t->k[$operand] === \__McTokId::T_LNUMBER && $t->content($operand) === '0';
        $isFalse = $t->k[$operand] === \__McTokId::T_STRING && \strtolower($t->content($operand)) === 'false';
        if (!$isZero && !$isFalse) {
            return null;
        }
        $prec = $t->meaningful($operand, $dir);
        if ($prec >= 0 && self::higher($t, $prec)) {
            return null;
        }
        return [$op, $operand];
    }

    private static function higher(FlatTokens $t, int $q): bool
    {
        if ($t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_DEC || $k === \__McTokId::T_INC || $k === \__McTokId::T_INSTANCEOF || $k === \__McTokId::T_IS_GREATER_OR_EQUAL
            || $k === \__McTokId::T_IS_SMALLER_OR_EQUAL || $k === \__McTokId::T_POW || $k === \__McTokId::T_SL || $k === \__McTokId::T_SR
            || $k === 33 || $k === 37 || $k === 42 || $k === 43 || $k === 45 || $k === 46 || $k === 47 || $k === 60 || $k === 62 || $k === 126;
    }
}
