<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\SingleLineThrowFixer (priority 36). A `throw` statement's line breaks (up to the `)` / `]` / `,` / `;` / `?>` that
 * ends it, `{` and closers stop the scan) become a space, or nothing next to `(`, `[`, `::`, `->`, `)`, `]`, `,`, `;`; `//` and `#` comments turn into block
 * comments (the break behind them goes). Ported on a whole-file FlatTokens.
 */
final class SingleLineThrow extends FlatRule
{
    /** @var array<int, bool> effective ids of block starts / ends (Tokens::getBlockEdgeDefinitions) */
    private array $start = [];
    /** @var array<int, bool> */
    private array $stop = [];

    public function __construct()
    {
        parent::__construct();
        foreach ([40, 91, 123, CT::ARRAY_BRACKET_OPEN, CT::DYNAMIC_PROP_BRACE_OPEN, CT::DYNAMIC_VAR_BRACE_OPEN, CT::ARRAY_INDEX_BRACE_OPEN, CT::GROUP_IMPORT_BRACE_OPEN,
            CT::DESTRUCTURING_BRACKET_OPEN, CT::CLASS_INSTANTIATION_PARENTHESIS_OPEN, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN,
            CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN, \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES, CT::PROPERTY_HOOK_BRACE_OPEN, \__McTokId::T_ATTRIBUTE] as $k) {
            $this->start[$k] = true;
        }
        foreach ([41, 93, 125, CT::ARRAY_BRACKET_CLOSE, CT::DYNAMIC_PROP_BRACE_CLOSE, CT::DYNAMIC_VAR_BRACE_CLOSE, CT::ARRAY_INDEX_BRACE_CLOSE, CT::GROUP_IMPORT_BRACE_CLOSE,
            CT::DESTRUCTURING_BRACKET_CLOSE, CT::CLASS_INSTANTIATION_PARENTHESIS_CLOSE, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE,
            CT::DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE, CT::DOLLAR_CLOSE_CURLY_BRACES, CT::PROPERTY_HOOK_BRACE_CLOSE, CT::ATTRIBUTE_CLOSE] as $k) {
            $this->stop[$k] = true;
        }
    }

    public function name(): string
    {
        return 'single_line_throw';
    }

    public function priority(): int
    {
        return 36;
    }

    /** @var int[][] the throws whose statement holds a line break or a comment: [throw, end candidate] */
    private array $work = [];

    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        $this->work = [];
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_THROW || $s->ct[$i] !== 0) {
                continue;
            }
            $e = $this->endCandidate($s, $i);
            for ($j = $i + 1; $j < $e && $j < $n; $j++) {
                if (($s->gapFlags[$j] & (Gap::G_NL | Gap::G_COMMENT)) !== 0) {
                    $this->work[] = [$i, $e];
                    break;
                }
            }
        }
        return $this->work !== [];
    }

    protected function window(Source $s): array
    {
        $a = $this->work[0][0];
        $b = $a;
        foreach ($this->work as $w) {
            $b = \max($b, $w[1]);
        }
        return [$a, \min($b, $s->n - 1), false];
    }

    /** the end candidate of the throw at Source index $i: the stopping token */
    private function endCandidate(Source $s, int $i): int
    {
        $n = $s->n;
        $j = $i + 1;
        while ($j < $n) {
            $c = $s->ct[$j];
            $k = $s->kind[$j];
            $id = $c !== 0 ? $c : $k;
            if ($c === 0 && ($k === 41 || $k === 93 || $k === 44 || $k === 59 || $k === \__McTokId::T_CLOSE_TAG)) {
                return $j;
            }
            if (isset($this->stop[$id]) || $id === 123) {
                return $j;
            }
            if (isset($this->start[$id]) && $s->pair[$j] > $j) {
                $j = $s->pair[$j];
            }
            $j++;
        }
        return $n;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $n = $s->n;
        foreach ($this->work as [$i, $e]) {
            if ($e >= $n) {
                continue;
            }
            $this->trim($t, $t->pos[$i], $t->meaningful($t->pos[$e], -1));
        }
    }

    private function trim(FlatTokens $t, int $start, int $end): void
    {
        for ($q = $start; $q < $end; $q++) {
            if ($t->isComment($q)) {
                $c = $t->c[$q];
                if (\Fmt\Trivia::isDoc($c)) {
                    continue;
                }
                if (\str_starts_with($c, '//')) {
                    $c = '/*' . \substr($c, 2) . ' */';
                    $t->clearAt($q + 1);
                } elseif ($c[0] === '#') {
                    $c = '/*' . \substr($c, 1) . ' */';
                    $t->clearAt($q + 1);
                } elseif (\Fmt\Trivia::pmatch('/\R/', $c)) {
                    $c = \Fmt\Trivia::preplace('/\R/', ' ', $c);
                }
                $t->c[$q] = $c;
                continue;
            }
            if (!$t->isWs($q) || !\Fmt\Trivia::pmatch('/\R/', $t->c[$q])) {
                continue;
            }
            $p = $t->nonEmptySibling($q, -1);
            if ($this->clearBefore($t, $p)) {
                $t->clearAt($q);
                continue;
            }
            $nx = $t->nonEmptySibling($q, 1);
            if ($this->clearAfter($t, $nx) && !self::is($t, $p, \__McTokId::T_FUNCTION)) {
                $t->clearAt($q);
                continue;
            }
            $t->c[$q] = ' ';
        }
    }

    private function clearBefore(FlatTokens $t, int $q): bool
    {
        return $q >= 0 && $t->i[$q] !== -1 && ($t->k[$q] === 91 || $t->k[$q] === 40 || $t->k[$q] === \__McTokId::T_DOUBLE_COLON
            || $t->k[$q] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$q] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR);
    }

    private function clearAfter(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === 40 || $k === \__McTokId::T_DOUBLE_COLON || $k === 41 || $k === 93 || $k === 44 || $k === 59 || $k === \__McTokId::T_OBJECT_OPERATOR
            || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR;
    }
}
