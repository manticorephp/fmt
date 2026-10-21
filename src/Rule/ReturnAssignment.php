<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\DocBlock;
use Fmt\FlatBlocks;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer (priority -15). `$a = expr; return $a;` -> `return expr;` in a function body that has no
 * references, eval / include / global / static variables / variable variables / superglobals (the whole function is skipped, nested functions are fixed on their
 * own); an assignment inside a try whose catch / finally uses the variable stays. skip_named_var_tags: an assignment under a named `@var` docblock stays.
 * Whole-file FlatTokens port, nested functions first, candidates from the last to the first.
 */
final class ReturnAssignment extends FlatRule
{
    private FlatBlocks $blocks;
    private bool $matchFound = false;

    public function __construct(private bool $skipNamedVarTags)
    {
        parent::__construct();
        $this->blocks = new FlatBlocks();
    }

    public function name(): string
    {
        return 'return_assignment';
    }

    public function priority(): int
    {
        return -15;
    }

    protected function candidate(Source $s): bool
    {
        $f = false;
        for ($i = 0; $i < $s->n; $i++) {
            $f = $f || $s->kind[$i] === \__McTokId::T_FUNCTION;
        }
        if (!$f) {
            return false;
        }
        // a change needs `; return $v;` right behind an assignment to the same variable
        for ($i = 3; $i + 2 < $s->n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_RETURN || $s->kind[$i + 1] !== \__McTokId::T_VARIABLE || $s->kind[$i - 1] !== 59) {
                continue;
            }
            $e = $s->kind[$i + 2];
            if ($e !== 59 && $e !== \__McTokId::T_CLOSE_TAG) {
                continue;
            }
            $name = $s->text($i + 1);
            for ($j = $i - 2; $j > 0; $j--) {
                $x = $s->kind[$j];
                $plain = $s->ct[$j] === 0;
                if ($plain && $x === 61 && $s->kind[$j - 1] === \__McTokId::T_VARIABLE && \strcasecmp($s->text($j - 1), $name) === 0) {
                    return true;
                }
                if ($plain && ($x === 123 || $x === 59 || $x === \__McTokId::T_OPEN_TAG || $x === \__McTokId::T_OPEN_TAG_WITH_ECHO)) {
                    break;
                }
                if ($plain && $x === 125) {
                    return true; // a lambda / anonymous class / match body: not decided here
                }
            }
        }
        return false;
    }

    private static function amp(FlatTokens $t, int $q): bool
    {
        return $t->i[$q] !== -1 && ($t->k[$q] === 38 || $t->k[$q] === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $t->k[$q] === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG);
    }

    /** Tokens::getNextTokenOfKind for a set of effective ids; -1 when none */
    private static function nextOfAny(FlatTokens $t, int $q, array $ids): int
    {
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && \in_array($t->k[$j], $ids, true)) {
                return $j;
            }
        }
        return -1;
    }

    private static function prevOfAny(FlatTokens $t, int $q, array $ids): int
    {
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && \in_array($t->k[$j], $ids, true)) {
                return $j;
            }
        }
        return -1;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->matchFound = \in_array(\__McTokId::T_MATCH, $t->k, true);
        $tokenCount = $t->count();
        for ($index = 1; $index < $tokenCount; ++$index) {
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $next = $t->meaningful($index, 1);
            if ($next >= 0 && self::is($t, $next, CT::RETURN_REF)) {
                continue;
            }
            $open = self::nextOfAny($t, $index, [123, 59]);
            if ($open < 0) {
                return;
            }
            if ($t->k[$open] === 59) {
                $index = $open - 1;
                continue;
            }
            $close = $t->blockEnd($open, 125);
            if ($close < 0) {
                return;
            }
            $total = 0;
            do {
                $added = $this->fixFunction($t, $index, $open, $close);
                $close += $added;
                $total += $added;
            } while ($added > 0);
            $index = $close;
            $tokenCount += $total;
        }
    }

    private function fixFunction(FlatTokens $t, int $functionIndex, int $open, int $close): int
    {
        $inserted = 0;
        /** @var int[] $candidates */
        $candidates = [];
        $risky = false;
        $nm = $t->meaningful($functionIndex, 1);
        if ($nm >= 0 && self::is($t, $nm, CT::RETURN_REF)) {
            $risky = true;
        }
        for ($index = $functionIndex + 1; $index < $open; ++$index) {
            if (self::amp($t, $index)) {
                $risky = true;
                break;
            }
        }
        for ($index = $open + 1; $index < $close; ++$index) {
            if (self::is($t, $index, \__McTokId::T_FUNCTION)) {
                $nestedOpen = self::nextOfAny($t, $index, [123, 59]);
                if ($nestedOpen < 0) {
                    return $inserted;
                }
                if ($t->k[$nestedOpen] === 59) {
                    $index = $nestedOpen - 1;
                    continue;
                }
                $nestedClose = $t->blockEnd($nestedOpen, 125);
                if ($nestedClose < 0) {
                    return $inserted;
                }
                $added = $this->fixFunction($t, $index, $nestedOpen, $nestedClose);
                $index = $nestedClose + $added;
                $close += $added;
                $inserted += $added;
            }
            if ($risky) {
                continue;
            }
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if (self::amp($t, $index)) {
                $risky = true;
                continue;
            }
            if ($k === \__McTokId::T_RETURN) {
                $candidates[] = $index;
                continue;
            }
            if ($k === CT::DYNAMIC_VAR_BRACE_OPEN || $k === \__McTokId::T_EVAL || $k === \__McTokId::T_GLOBAL || $k === \__McTokId::T_INCLUDE
                || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE) {
                $risky = true;
                continue;
            }
            if ($k === \__McTokId::T_STATIC) {
                $nx = $t->meaningful($index, 1);
                if ($nx < 0 || !self::is($t, $nx, \__McTokId::T_FUNCTION)) {
                    $risky = true;
                    continue;
                }
            }
            if ($k === 36) {
                $nx = $t->meaningful($index, 1);
                if ($nx >= 0 && self::is($t, $nx, \__McTokId::T_VARIABLE)) {
                    $risky = true;
                    continue;
                }
            }
            if ($k === \__McTokId::T_VARIABLE && \in_array(\strtoupper($t->content($index)), ['$_COOKIE', '$_ENV', '$_FILES', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_SESSION', '$GLOBALS'], true)) {
                $risky = true;
                continue;
            }
        }
        if ($risky) {
            return $inserted;
        }
        for ($i = \count($candidates) - 1; $i >= 0; --$i) {
            $index = $candidates[$i];
            $returnVar = $t->meaningful($index, 1);
            if ($returnVar < 0 || !self::is($t, $returnVar, \__McTokId::T_VARIABLE)) {
                continue;
            }
            $endReturnVar = $t->meaningful($returnVar, 1);
            if ($endReturnVar < 0 || !(self::is($t, $endReturnVar, 59) || self::is($t, $endReturnVar, \__McTokId::T_CLOSE_TAG))) {
                continue;
            }
            $assignVarEnd = $t->meaningful($index, -1);
            if ($assignVarEnd < 0 || !self::is($t, $assignVarEnd, 59)) {
                continue;
            }
            while (true) {
                $prevMeaningful = $t->meaningful($assignVarEnd, -1);
                if ($prevMeaningful < 0 || !self::is($t, $prevMeaningful, 41)) {
                    break;
                }
                $assignVarEnd = $t->blockStart($prevMeaningful, 40);
                if ($assignVarEnd < 0) {
                    return $inserted;
                }
            }
            $assignOp = self::prevOfAny($t, $assignVarEnd, [61, 59, 123, 125, \__McTokId::T_OPEN_TAG, \__McTokId::T_OPEN_TAG_WITH_ECHO]);
            if ($assignOp < 0) {
                continue;
            }
            if (self::is($t, $assignOp, 125)) {
                $start = $this->closeBracePartOfDefinition($t, $assignOp);
                if ($start < 0) {
                    continue;
                }
                $assignOp = $t->meaningful($start, -1);
                if ($assignOp < 0) {
                    continue;
                }
            }
            if (!self::is($t, $assignOp, 61)) {
                continue;
            }
            $assignVar = $t->meaningful($assignOp, -1);
            if ($assignVar < 0 || !self::is($t, $assignVar, \__McTokId::T_VARIABLE) || \strcasecmp($t->content($assignVar), $t->content($returnVar)) !== 0) {
                continue;
            }
            $before = $t->meaningful($assignVar, -1);
            if ($before < 0 || !(self::is($t, $before, 59) || self::is($t, $before, 123) || self::is($t, $before, 125))) {
                continue;
            }
            if ($this->skipNamedVarTags && $this->hasNamedVarTag($t, $assignVar, $open)) {
                continue;
            }
            if ($this->usedInCatchOrFinally($t, $returnVar, $open, $close)) {
                continue;
            }
            $inserted += $this->simplify($t, $assignVar, $assignOp, $index, $endReturnVar);
        }
        return $inserted;
    }

    private function simplify(FlatTokens $t, int $assignVar, int $assignOp, int $returnIndex, int $returnVarEnd): int
    {
        $inserted = 0;
        $originalIndent = $t->isWs($assignVar - 1) ? $t->c[$assignVar - 1] : null;
        if (self::is($t, $returnVarEnd, 59)) {
            self::clearMerge($t, $returnVarEnd);
        }
        for ($i = $returnIndex; $i <= $returnVarEnd - 1; ++$i) {
            $this->clearIfSave($t, $i);
        }
        if ($t->isWs($returnIndex - 1)) {
            $content = $t->c[$returnIndex - 1];
            $pos = \strrpos($content, "\n");
            $t->c[$returnIndex - 1] = $pos === false ? ' ' : \substr($content, $pos);
        }
        for ($i = $assignVar; $i <= $assignOp; ++$i) {
            $this->clearIfSave($t, $i);
        }
        $t->insertAt($assignVar, [FlatTokens::code(\__McTokId::T_RETURN, 'return')]);
        ++$inserted;
        if ($originalIndent !== null && $t->isWs($assignVar - 1) && $originalIndent !== $t->c[$assignVar - 1]) {
            $t->c[$assignVar - 1] = $originalIndent;
        }
        $next = $t->nonEmptySibling($assignVar, 1);
        if ($next >= 0 && !$t->isWs($next)) {
            $t->insertAt($next, [FlatTokens::ws(' ')]);
            ++$inserted;
        }
        return $inserted;
    }

    private function clearIfSave(FlatTokens $t, int $q): void
    {
        if ($t->isComment($q)) {
            return;
        }
        if ($t->isWs($q)) {
            $p = $t->nonWhitespace($q, -1);
            if ($p >= 0 && $t->isComment($p)) {
                return;
            }
        }
        self::clearMerge($t, $q);
    }

    /** the index of the construct a `}` closes when it is an anonymous class, lambda or match; -1 otherwise */
    private function closeBracePartOfDefinition(FlatTokens $t, int $close): int
    {
        $open = $t->blockStart($close, 123);
        if ($open < 0) {
            return -1;
        }
        $c = $this->openBraceOfLambda($t, $open);
        if ($c >= 0) {
            return $c;
        }
        $c = $this->openBraceOfAnonymousClass($t, $open);
        return $c >= 0 ? $c : $this->openBraceOfMatch($t, $open);
    }

    private function openBraceOfAnonymousClass(FlatTokens $t, int $index): int
    {
        do {
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                return -1;
            }
        } while (self::isAnyId($t, $index, [44, \__McTokId::T_STRING, \__McTokId::T_IMPLEMENTS, \__McTokId::T_EXTENDS, \__McTokId::T_NS_SEPARATOR]));
        if (self::is($t, $index, 41)) {
            $index = $t->blockStart($index, 40);
            $index = $index < 0 ? -1 : $t->meaningful($index, -1);
            if ($index < 0) {
                return -1;
            }
        }
        if (!self::is($t, $index, \__McTokId::T_CLASS) || !$this->isAnonymousClass($t, $index)) {
            return -1;
        }
        return self::prevOfAny($t, $index, [\__McTokId::T_NEW]);
    }

    private function openBraceOfLambda(FlatTokens $t, int $index): int
    {
        $index = $t->meaningful($index, -1);
        if ($index < 0 || !self::is($t, $index, 41)) {
            return -1;
        }
        $index = $t->blockStart($index, 40);
        $index = $index < 0 ? -1 : $t->meaningful($index, -1);
        if ($index < 0) {
            return -1;
        }
        if (self::is($t, $index, CT::USE_LAMBDA)) {
            $index = self::prevOfAny($t, $index, [41]);
            $index = $index < 0 ? -1 : $t->blockStart($index, 40);
            $index = $index < 0 ? -1 : $t->meaningful($index, -1);
            if ($index < 0) {
                return -1;
            }
        }
        if (self::is($t, $index, CT::RETURN_REF)) {
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                return -1;
            }
        }
        if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
            return -1;
        }
        $static = $t->meaningful($index, -1);
        return $static >= 0 && self::is($t, $static, \__McTokId::T_STATIC) ? $static : $index;
    }

    private function openBraceOfMatch(FlatTokens $t, int $index): int
    {
        if (!$this->matchFound) {
            return -1;
        }
        $index = $t->meaningful($index, -1);
        if ($index < 0 || !self::is($t, $index, 41)) {
            return -1;
        }
        $index = $t->blockStart($index, 40);
        $index = $index < 0 ? -1 : $t->meaningful($index, -1);
        return $index >= 0 && self::is($t, $index, \__McTokId::T_MATCH) ? $index : -1;
    }

    /** TokensAnalyzer::isAnonymousClass for a T_CLASS at $index */
    private function isAnonymousClass(FlatTokens $t, int $index): bool
    {
        $index = $t->meaningful($index, -1);
        if ($index >= 0 && self::is($t, $index, \__McTokId::T_READONLY)) {
            $index = $t->meaningful($index, -1);
        }
        while ($index >= 0 && self::is($t, $index, CT::ATTRIBUTE_CLOSE)) {
            $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
            $index = $index < 0 ? -1 : $t->meaningful($index, -1);
        }
        return $index >= 0 && self::is($t, $index, \__McTokId::T_NEW);
    }

    /** @param int[] $ids */
    private static function isAnyId(FlatTokens $t, int $q, array $ids): bool
    {
        return $t->i[$q] !== -1 && \in_array($t->k[$q], $ids, true);
    }

    private function hasNamedVarTag(FlatTokens $t, int $assignVar, int $open): bool
    {
        $doc = -1;
        for ($j = $assignVar - 1; $j >= 0; $j--) {
            if ($t->isComment($j) && Trivia::isDoc($t->c[$j])) {
                $doc = $j;
                break;
            }
        }
        if ($doc < 0 || $doc <= $open) {
            return false;
        }
        $db = new DocBlock($t->c[$doc]);
        foreach ($db->getAnnotationsOfType(['var', 'psalm-var', 'phpstan-var']) as $a) {
            if ($a->getVariableName() !== null) {
                return true;
            }
        }
        return false;
    }

    private function nextSameVariable(FlatTokens $t, int $from, int $returnVar): int
    {
        $text = $t->content($returnVar);
        $m = $t->count();
        for ($j = $from + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_VARIABLE && $t->content($j) === $text) {
                return $j;
            }
        }
        return -1;
    }

    private function usedInCatchOrFinally(FlatTokens $t, int $returnVar, int $open, int $close): bool
    {
        $try = self::prevOfAny($t, $returnVar, [\__McTokId::T_TRY]);
        if ($try < 0 || $try <= $open) {
            return false;
        }
        $tryOpen = self::nextOfAny($t, $try, [123]);
        $tryClose = $tryOpen < 0 ? -1 : $t->blockEnd($tryOpen, 125);
        if ($tryClose < 0) {
            return false;
        }
        $next = $t->meaningful($tryClose, 1);
        if ($next < 0) {
            return false;
        }
        while (self::is($t, $next, \__McTokId::T_CATCH)) {
            $catchOpen = self::nextOfAny($t, $next, [123]);
            $catchClose = $catchOpen < 0 ? -1 : $t->blockEnd($catchOpen, 125);
            if ($catchClose < 0 || $catchClose >= $close) {
                return false;
            }
            $var = $this->nextSameVariable($t, $catchOpen, $returnVar);
            if ($var >= 0 && $var < $catchClose) {
                return true;
            }
            $next = $t->meaningful($catchClose, 1);
            if ($next < 0) {
                return false;
            }
        }
        if (!self::is($t, $next, \__McTokId::T_FINALLY)) {
            return false;
        }
        $finally = $next;
        if ($finally >= $close) {
            return false;
        }
        $fOpen = self::nextOfAny($t, $finally, [123]);
        $fClose = $fOpen < 0 ? -1 : $t->blockEnd($fOpen, 125);
        if ($fClose < 0) {
            return false;
        }
        $var = $this->nextSameVariable($t, $fOpen, $returnVar);
        return $var >= 0 && $var < $fClose;
    }
}
