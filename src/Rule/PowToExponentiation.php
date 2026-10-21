<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\PowToExponentiationFixer (priority 32, risky). `pow($a, $b)` -> `$a ** $b` (an argument that is not a simple operand gets parentheses; the
 * first comma after the first argument's start is the one replaced, nested calls included, as upstream). Whole-file FlatTokens port, last call first with upstream's
 * running count of inserted parentheses for nested calls.
 */
final class PowToExponentiation extends FlatRule
{
    public function name(): string
    {
        return 'pow_to_exponentiation';
    }

    public function priority(): int
    {
        return 32;
    }

    protected function candidate(Source $s): bool
    {
        return $s->n > 7 && self::hasCallOf($s, ['pow']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $cands = [];
        $end = $t->count() - 6;
        for ($i = 1; $i < $end; $i++) {
            $c = $this->findCall($s, $t, 'pow', $i, $end);
            if ($c === null) {
                break;
            }
            $i = $c[1];
            $cands[] = $c;
        }
        $added = 0;
        $prevClose = $t->count();
        for ($ci = \count($cands) - 1; $ci >= 0; $ci--) {
            [$fn, $open, $close] = $cands[$ci];
            if ($prevClose < $close) {
                $prevClose = $close;
                $close += $added;
            } else {
                $prevClose = $close;
                $added = 0;
            }
            $args = self::callArguments($t, $open, $close);
            if (\count($args) !== 2) {
                continue;
            }
            $ell = false;
            for ($i = $open; $i < $close; $i++) {
                if ($t->i[$i] !== -1 && $t->k[$i] === \__McTokId::T_ELLIPSIS) {
                    $ell = true;
                    break;
                }
            }
            if ($ell) {
                continue;
            }
            $named = false;
            foreach ($args as $a => $b) {
                $fm = $t->meaningful($a - 1, 1);
                if ($fm >= 0 && $t->k[$fm] === CT::NAMED_ARGUMENT_NAME) {
                    $named = true;
                }
            }
            if ($named) {
                $s->abort = true; // `(a: 1)**(b: 2)`: upstream's result does not lint, the file stays as it is
                continue;
            }
            $added += $this->fix($t, $fn, $open, $close, $args);
        }
    }

    /** @param array<int, int> $args */
    private function fix(FlatTokens $t, int $fn, int $open, int $close, array $args): int
    {
        $starts = \array_keys($args);
        $comma = -1;
        $m = $t->count();
        for ($j = $args[$starts[0]] + 1; $j < $m; $j++) { // reset($arguments) is the END index of the first argument
            if ($t->i[$j] !== -1 && $t->k[$j] === 44) {
                $comma = $j;
                break;
            }
        }
        self::setCode($t, $comma, \__McTokId::T_POW, '**');
        $t->clearAt($close);
        $prev = $t->meaningful($close, -1);
        if (self::is($t, $prev, 44)) {
            $t->clearAt($prev);
        }
        $added = 0;
        for ($x = \count($starts) - 1; $x >= 0; $x--) {
            $a = $starts[$x];
            $b = $args[$a];
            if ($this->parenthesisNeeded($t, $a, $b)) {
                $t->insertAt($b + 1, [FlatTokens::code(41, ')')]);
                $t->insertAt($a, [FlatTokens::code(40, '(')]);
                $added += 2;
            }
        }
        $t->clearAt($open);
        self::clearMerge($t, $fn);
        $pm = $t->meaningful($fn, -1);
        if (self::is($t, $pm, \__McTokId::T_NS_SEPARATOR)) {
            $t->clearAt($pm);
        }
        return $added;
    }

    private function parenthesisNeeded(FlatTokens $t, int $a, int $b): bool
    {
        for ($i = $a; $i <= $b; $i++) {
            $k = $t->k[$i];
            if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT) {
                continue;
            }
            if ($k === \__McTokId::T_DNUMBER || $k === \__McTokId::T_LNUMBER || $k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_STRING || $k === \__McTokId::T_CONSTANT_ENCAPSED_STRING
                || $k === \__McTokId::T_DOUBLE_CAST || $k === \__McTokId::T_INT_CAST || $k === \__McTokId::T_INC || $k === \__McTokId::T_DEC || $k === \__McTokId::T_NS_SEPARATOR
                || $k === \__McTokId::T_DOUBLE_COLON || $k === \__McTokId::T_LINE || $k === CT::NAMESPACE_OPERATOR || $k === \__McTokId::T_OBJECT_OPERATOR
                || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                continue;
            }
            $det = self::blockEdge($k);
            if ($det !== null) {
                $i = $t->blockEnd($i, TypeWalk::closer($det[0]));
                continue;
            }
            if ($k === 36) {
                $i = $t->meaningful($i, 1);
                if ($t->k[$i] === CT::DYNAMIC_VAR_BRACE_OPEN) {
                    $i = $t->blockEnd($i, CT::DYNAMIC_VAR_BRACE_CLOSE);
                    continue;
                }
            }
            if ($k === 43 && $t->meaningful($i, -1) < $a) {
                continue;
            }
            return true;
        }
        return false;
    }
}
