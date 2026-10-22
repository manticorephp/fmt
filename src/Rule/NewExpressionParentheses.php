<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\NewExpressionParenthesesFixer (priority 29). A `new` expression followed by `->` `?->` or `::` is wrapped in
 * parentheses (use_parentheses true) or not (false: the CT instantiation parentheses go, their whitespace merged). Whole-file FlatTokens port, last `new` first.
 */
final class NewExpressionParentheses extends FlatRule
{
    public function __construct(private bool $use)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'new_expression_parentheses';
    }

    public function priority(): int
    {
        return 29;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_NEW) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 3; $index > 0; --$index) {
            if (!self::is($t, $index, \__McTokId::T_NEW)) {
                continue;
            }
            $start = $t->meaningful($index, 1);
            if ($start < 0) {
                return;
            }
            if (self::is($t, $start, \__McTokId::T_CLASS)) {
                $next = $t->meaningful($start, 1);
                if (self::is($t, $next, 40)) {
                    $next = $t->blockEnd($next, 41);
                } else {
                    $next = $start;
                }
                $bodyStart = self::nextOf($t, $next, 123);
                if ($bodyStart < 0) {
                    continue;
                }
                $bodyEnd = $t->blockEnd($bodyStart, 125);
                $this->apply($t, $index, $bodyEnd);
                continue;
            }
            $classEnd = $this->classEnd($t, $start);
            if ($classEnd < 0) {
                continue;
            }
            $next = $t->meaningful($classEnd, 1);
            if (!self::is($t, $next, 40)) {
                continue;
            }
            $argsEnd = $t->blockEnd($next, 41);
            $this->apply($t, $index, $argsEnd);
        }
    }

    private function apply(FlatTokens $t, int $exprStart, int $exprEnd): void
    {
        $prev = $t->meaningful($exprStart, -1);
        $next = $t->meaningful($exprEnd, 1);
        if ($this->use) {
            if (self::is($t, $prev, CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN) && self::is($t, $next, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE)) {
                return;
            }
            if (!self::opAfter($t, $next)) {
                return;
            }
            $t->insertAt($exprStart, [FlatTokens::code(CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN, '(')]);
            $t->insertAt($exprEnd + 2, [FlatTokens::code(CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE, ')')]);
            return;
        }
        if (!self::is($t, $prev, CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN) || !self::is($t, $next, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE)) {
            return;
        }
        $op = $t->meaningful($next, 1);
        if (!self::opAfter($t, $op)) {
            return;
        }
        self::clearMerge($t, $prev);
        self::clearMerge($t, $next);
    }

    private static function opAfter(FlatTokens $t, int $q): bool
    {
        return $q >= 0 && $t->i[$q] !== -1 && ($t->k[$q] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$q] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR
            || $t->k[$q] === \__McTokId::T_DOUBLE_COLON);
    }

    private static function chainKind(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR || $k === CT::NAMESPACE_OPERATOR || $k === \__McTokId::T_VARIABLE || $k === 36
            || $k === CT::DYNAMIC_VAR_BRACE_OPEN || $k === 91 || $k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR
            || $k === \__McTokId::T_DOUBLE_COLON;
    }

    private function classEnd(FlatTokens $t, int $index): int
    {
        if (self::is($t, $index, 40)) {
            return $t->blockEnd($index, 41);
        }
        if (!self::chainKind($t, $index)) {
            return -1;
        }
        while (self::chainKind($t, $index)) {
            $det = self::blockEdge($t->k[$index]);
            if ($det !== null) {
                $index = $t->blockEnd($index, TypeWalk::closer($det[0]));
            }
            $index = $t->meaningful($index, 1);
            if ($index < 0) {
                return -1;
            }
        }
        return $index - 1;
    }
}
