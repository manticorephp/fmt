<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Operator\NewWithParenthesesFixer (priority 38). Walks `new` from the end; from each, the first token
 * of NEXT_TOKEN_KINDS (raw scan, the `(` of a later `new` inserted earlier counts, a deleted pair does not) decides: a T_CLASS is an
 * anonymous class (option anonymous_class, the token after `class`), else `[..]` index groups are skipped and option named_class applies.
 * Ensure = `()` right after the previous code token unless the token is `(` or `->` / `?->`; remove = an empty `()` not followed by
 * an object operator goes, its trivia stays.
 */
final class NewWithParentheses extends EditRule
{
    /** @var array<int, bool> NEXT_TOKEN_KINDS: plain kinds (single chars, T_*), ct ids separately */
    private array $stop = [];
    /** @var array<int, bool> */
    private array $stopCt = [];
    /** @var array<int, bool> source token index => `()` inserted right after it */
    private array $ins = [];
    /** @var array<int, bool> */
    private array $del = [];

    public function __construct(private bool $named = true, private bool $anonymous = true)
    {
        foreach (['?', ';', ',', '(', ')', '[', ']', ':', '<', '>', '+', '-', '*', '/', '%', '&', '^', '|'] as $c) {
            $this->stop[\ord($c)] = true;
        }
        foreach ([\__McTokId::T_CLASS, \__McTokId::T_IS_SMALLER_OR_EQUAL, \__McTokId::T_IS_GREATER_OR_EQUAL, \__McTokId::T_IS_EQUAL,
            \__McTokId::T_IS_NOT_EQUAL, \__McTokId::T_IS_IDENTICAL, \__McTokId::T_IS_NOT_IDENTICAL, \__McTokId::T_CLOSE_TAG,
            \__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR, \__McTokId::T_BOOLEAN_AND, \__McTokId::T_BOOLEAN_OR,
            \__McTokId::T_SL, \__McTokId::T_SR, \__McTokId::T_INSTANCEOF, \__McTokId::T_AS, \__McTokId::T_DOUBLE_ARROW, \__McTokId::T_POW,
            \__McTokId::T_SPACESHIP, \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG] as $k) {
            $this->stop[$k] = true;
        }
        foreach ([CT::ARRAY_BRACKET_OPEN, CT::ARRAY_BRACKET_CLOSE, CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE] as $k) {
            $this->stopCt[$k] = true;
        }
    }

    public function name(): string
    {
        return 'new_with_parentheses';
    }

    public function priority(): int
    {
        return 38;
    }

    /** Token::equals over a token-kind list: a single char only when no transformer retyped it, a T_* id likewise, a CT id by ct */
    private function isStop(Source $s, int $i): bool
    {
        $c = $s->ct[$i];
        if ($c !== 0) {
            return isset($this->stopCt[$c]);
        }
        return isset($this->stop[$s->kind[$i]]);
    }

    private function isParen(Source $s, int $i): bool
    {
        return $s->kind[$i] === 40 && $s->ct[$i] === 0;
    }

    private function isObjOp(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        return $k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $this->ins = [];
        $this->del = [];
        for ($i = $n - 1; $i > 0; $i--) {
            if ($s->kind[$i] !== \__McTokId::T_NEW) {
                continue;
            }
            $j = $i + 1;
            $virtual = false; // the next token is the `(` inserted after token j - 1
            for (; $j < $n; $j++) {
                if (isset($this->del[$j])) {
                    continue;
                }
                if ($this->isStop($s, $j)) {
                    break;
                }
                if (isset($this->ins[$j])) {
                    $virtual = true;
                    break;
                }
            }
            if ($virtual || $j >= $n) {
                continue; // `(`: nothing to do
            }
            if ($s->kind[$j] === \__McTokId::T_CLASS && $s->ct[$j] === 0) {
                if ($j + 1 < $n) {
                    $this->apply($s, $j + 1, $this->anonymous);
                }
                continue;
            }
            while (($s->kind[$j] === 91 && $s->ct[$j] === 0) || $s->ct[$j] === CT::ARRAY_INDEX_BRACE_OPEN) {
                $j = $s->pair[$j] + 1;
                if ($j >= $n) {
                    continue 2;
                }
            }
            $this->apply($s, $j, $this->named);
        }
        $this->ins = [];
        $this->del = [];
    }

    private function apply(Source $s, int $j, bool $ensure): void
    {
        if ($ensure) {
            if (!$this->isParen($s, $j) && !$this->isObjOp($s, $j)) {
                $this->ins[$j - 1] = true;
                $s->edit(Source::gapAt($j), Source::gapAt($j), '()');
            }
            return;
        }
        if (!$this->isParen($s, $j) || $j + 1 >= $s->n || $s->kind[$j + 1] !== 41 || $s->ct[$j + 1] !== 0) {
            return;
        }
        if ($j + 2 < $s->n && $this->isObjOp($s, $j + 2)) {
            return;
        }
        $this->del[$j] = true;
        $this->del[$j + 1] = true;
        $s->edit(Source::tokAt($j), Source::tokAt($j + 1) + 1, $s->gapBefore[$j + 1]);
    }
}
