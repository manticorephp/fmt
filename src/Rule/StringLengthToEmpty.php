<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\StringLengthToEmptyFixer (priority 1, risky). `strlen($a) === 0` -> `$a === ''` (and the other comparisons of a length with 0 / 1 that
 * mean "empty" / "not empty"; Yoda forms too). Whole-file FlatTokens port, last call first.
 */
final class StringLengthToEmpty extends FlatRule
{
    public function name(): string
    {
        return 'string_length_to_empty';
    }

    public function priority(): int
    {
        return 1;
    }

    private static function opOfInterest(int $k): bool
    {
        return $k === \__McTokId::T_IS_IDENTICAL || $k === \__McTokId::T_IS_NOT_IDENTICAL || $k === \__McTokId::T_IS_SMALLER_OR_EQUAL || $k === \__McTokId::T_IS_GREATER_OR_EQUAL
            || $k === 60 || $k === 62;
    }

    /** a `strlen(` call with such a comparison operator right before the name (or its `\`) or right after the parentheses */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 1; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_STRING || $s->kind[$i + 1] !== 40 || $s->end[$i] - $s->start[$i] !== 6 || \strtolower($s->text($i)) !== 'strlen') {
                continue;
            }
            $p = $i - 1;
            if ($p >= 0 && $s->kind[$p] === \__McTokId::T_NS_SEPARATOR) {
                $p--;
            }
            if ($p >= 0 && $s->ct[$p] === 0 && self::opOfInterest($s->kind[$p])) {
                return true;
            }
            $c = $s->pair[$i + 1];
            if ($c + 1 < $n && $s->ct[$c + 1] === 0 && self::opOfInterest($s->kind[$c + 1])) {
                return true;
            }
        }
        return false;
    }

    /** the replacement kind for [operator, operand '0' | '1'], 0 when there is none */
    private static function yoda(int $op, bool $zero): int
    {
        if ($zero) {
            if ($op === \__McTokId::T_IS_IDENTICAL || $op === \__McTokId::T_IS_GREATER_OR_EQUAL) {
                return \__McTokId::T_IS_IDENTICAL;
            }
            if ($op === \__McTokId::T_IS_NOT_IDENTICAL || $op === 60) {
                return \__McTokId::T_IS_NOT_IDENTICAL;
            }
            return 0;
        }
        if ($op === \__McTokId::T_IS_SMALLER_OR_EQUAL) {
            return \__McTokId::T_IS_NOT_IDENTICAL;
        }
        if ($op === 62) {
            return \__McTokId::T_IS_IDENTICAL;
        }
        return 0;
    }

    private static function notYoda(int $op, bool $zero): int
    {
        if ($zero) {
            if ($op === \__McTokId::T_IS_IDENTICAL || $op === \__McTokId::T_IS_SMALLER_OR_EQUAL) {
                return \__McTokId::T_IS_IDENTICAL;
            }
            if ($op === \__McTokId::T_IS_NOT_IDENTICAL || $op === 62) {
                return \__McTokId::T_IS_NOT_IDENTICAL;
            }
            return 0;
        }
        if ($op === \__McTokId::T_IS_GREATER_OR_EQUAL) {
            return \__McTokId::T_IS_NOT_IDENTICAL;
        }
        if ($op === 60) {
            return \__McTokId::T_IS_IDENTICAL;
        }
        return 0;
    }

    private static function operand(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1 || $t->k[$q] !== \__McTokId::T_LNUMBER) {
            return false;
        }
        $c = $t->content($q);
        return $c === '0' || $c === '1';
    }

    private static function opInterest(FlatTokens $t, int $q): bool
    {
        return $q >= 0 && $t->i[$q] !== -1 && self::opOfInterest($t->k[$q]);
    }

    private static function higher(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_INSTANCEOF || $k === \__McTokId::T_POW || $k === \__McTokId::T_SL || $k === \__McTokId::T_SR
            || $k === 33 || $k === 37 || $k === 42 || $k === 43 || $k === 45 || $k === 46 || $k === 47 || $k === 126 || $k === 63;
    }

    private static function keepParentheses(FlatTokens $t, int $open, int $close): bool
    {
        $i = $t->meaningful($open, 1);
        if (self::isCastKind($t->k[$i])) {
            $i = $t->meaningful($i, 1);
        }
        for (; $i < $close; $i++) {
            $k = $t->k[$i];
            if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT) {
                continue;
            }
            if ($t->i[$i] !== -1 && ($k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_STRING || $k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR)) {
                continue;
            }
            $det = $t->i[$i] === -1 ? null : self::blockEdge($k);
            if ($det !== null && $det[1] === 1) {
                $i = $t->blockEnd($i, TypeWalk::closer($det[0]));
                continue;
            }
            return true;
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $cands = [];
        $count = $t->count();
        for ($i = 0; $i < $count; $i++) {
            $c = $this->findCall($s, $t, 'strlen', $i, $count);
            if ($c === null) {
                break;
            }
            $i = $c[1];
            $cands[] = $c;
        }
        for ($ci = \count($cands) - 1; $ci >= 0; $ci--) {
            [$fn, $open, $close] = $cands[$ci];
            if (\count(self::callArguments($t, $open, $close)) !== 1) {
                continue;
            }
            $next = $t->meaningful($close, 1);
            $prev = $t->meaningful($fn, -1);
            $nsIdx = -1;
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                $nsIdx = $prev;
                $prev = $t->meaningful($prev, -1);
            }
            $opIdx = -1;
            $operandIdx = -1;
            $repl = 0;
            if (self::opInterest($t, $prev)) {
                $opIdx = $prev;
                $operandIdx = $t->meaningful($prev, -1);
                if (!self::operand($t, $operandIdx)) {
                    continue;
                }
                $repl = self::yoda($t->k[$opIdx], $t->content($operandIdx) === '0');
                if ($repl === 0) {
                    continue;
                }
                if (self::higher($t, $next)) {
                    continue;
                }
                if (self::higher($t, $t->meaningful($operandIdx, -1))) {
                    continue;
                }
            } elseif (self::opInterest($t, $next)) {
                $opIdx = $next;
                $operandIdx = $t->meaningful($next, 1);
                if (!self::operand($t, $operandIdx)) {
                    continue;
                }
                $repl = self::notYoda($t->k[$opIdx], $t->content($operandIdx) === '0');
                if ($repl === 0) {
                    continue;
                }
                if (self::higher($t, $t->meaningful($operandIdx, 1))) {
                    continue;
                }
                if (self::higher($t, $prev)) {
                    continue;
                }
            } else {
                continue;
            }
            $keep = self::keepParentheses($t, $open, $close);
            $text = $repl === \__McTokId::T_IS_IDENTICAL ? '===' : '!==';
            self::setCode($t, $operandIdx, \__McTokId::T_CONSTANT_ENCAPSED_STRING, "''");
            self::setCode($t, $opIdx, $repl, $text);
            if (!$keep) {
                self::clearMerge($t, $close);
                self::clearMerge($t, $open);
            }
            self::clearMerge($t, $fn);
            if ($nsIdx >= 0) {
                self::clearMerge($t, $nsIdx);
            }
        }
    }
}
