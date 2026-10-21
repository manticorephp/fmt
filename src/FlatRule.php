<?php

declare(strict_types=1);

namespace Fmt;

/**
 * An edit rule ported line by line from upstream on a whole-file FlatTokens (upstream's integer indexes over code, whitespace and comment tokens,
 * `clearAt` leaving an empty whitespace item). `candidate()` is the cheap Source-level test that nothing can change in a file (no load then);
 * `run()` edits the flat list; FlatTokens::emit records what changed as edits (and the Engine patches the Source).
 */
abstract class FlatRule extends EditRule
{
    protected FlatTokens $t;
    /** the algorithm only rewrites whitespace / comments between code tokens: the result goes back as gaps, no render and no re-parse */
    protected bool $gapsOnly = false;

    public function __construct()
    {
        $this->t = new FlatTokens();
    }

    abstract protected function candidate(Source $s): bool;

    abstract protected function run(Source $s, FlatTokens $t): void;

    public function prepare(Source $s): bool
    {
        return $this->candidate($s);
    }

    /**
     * The token range [a, b] (and whether the trailing trivia of the file) the algorithm may touch; the default is the whole file. A rule that
     * knows its constructs are local says so: loading and rendering are linear in the window.
     * @return array{0:int,1:int,2:bool}
     */
    protected function window(Source $s): array
    {
        return [0, $s->n - 1, true];
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        if ($n === 0) {
            return;
        }
        [$a, $b, $tail] = $this->window($s);
        $t = $this->t;
        $t->load($s, $a, $b, $tail);
        if ($this->gapsOnly) {
            $this->run($s, $t);
            $this->storeGaps($s, $t, $tail, $n);
            return;
        }
        $this->run($s, $t);
        $t->emit($s, $a, $b, $tail);
    }

    private function storeGaps(Source $s, FlatTokens $t, bool $tail, int $n): void
    {
        $m = $t->count();
        $acc = '';
        $first = true;
        for ($q = 0; $q < $m; $q++) {
            if ($t->i[$q] < 0) {
                $acc .= $t->c[$q];
                continue;
            }
            if (!$first) {
                $s->setGap($t->i[$q], $acc);
            }
            $first = false;
            $acc = '';
        }
        if ($tail) {
            $s->setGap($n, $acc);
        }
    }


    /** @var NativeFunctionCasing|null FunctionsAnalyzer::isGlobalFunctionCall over the Source (analysis cached per Source) */
    private ?Rule\NativeFunctionCasing $fnAnalyzer = null;

    /** FunctionsAnalyzer::isGlobalFunctionCall for the original T_STRING item at $q (an inserted token is not a call) */
    protected function isGlobalCall(Source $s, FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->k[$q] !== \__McTokId::T_STRING || $t->i[$q] < 0) {
            return false;
        }
        $nx = $t->meaningful($q, 1);
        if ($nx < 0 || $t->k[$nx] !== 40 || $t->i[$nx] === -1) {
            return false;
        }
        if ($this->fnAnalyzer === null) {
            $this->fnAnalyzer = new Rule\NativeFunctionCasing();
        }
        return $this->fnAnalyzer->isGlobalCallAt($s, $t->i[$q], \strtolower($t->content($q)));
    }

    /**
     * AbstractFunctionReferenceFixer::find: the next global call of function $lower (a T_STRING followed by `(`) at or after item $from, up to
     * item $end; null when there is none. Returns [name, open parenthesis, its closing parenthesis].
     * @return int[]|null
     */
    protected function findCall(Source $s, FlatTokens $t, string $lower, int $from, int $end): ?array
    {
        $m = $t->count();
        if ($end > $m - 1) {
            $end = $m - 1;
        }
        for ($j = $from; $j <= $end; $j++) {
            if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_STRING || \strtolower($t->content($j)) !== $lower) {
                continue;
            }
            $o = $t->meaningful($j, 1);
            if ($o < 0 || $o > $end || $t->k[$o] !== 40 || $t->i[$o] === -1) {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $j)) {
                $j = $o - 1;
                continue;
            }
            return [$j, $o, $t->blockEnd($o, 41)];
        }
        return null;
    }

    /**
     * ArgumentsAnalyzer::getArguments: start item => end item of every argument of the call whose parentheses are $open / $close.
     * @return array<int, int>
     */
    protected static function callArguments(FlatTokens $t, int $open, int $close): array
    {
        $out = [];
        $first = $t->meaningful($open, 1);
        if ($first >= 0 && $t->k[$first] === 41 && $t->i[$first] !== -1) {
            return $out;
        }
        $p = $open + 1;
        $as = $p;
        for (; $p < $close; $p++) {
            if ($t->i[$p] === -1) {
                continue;
            }
            $cl = TypeWalk::closer($t->k[$p]);
            if ($cl >= 0) {
                $p = $t->blockEnd($p, $cl);
                continue;
            }
            if ($t->k[$p] === 44) {
                if ($t->meaningful($p, 1) === $close) {
                    break;
                }
                $out[$as] = $p - 1;
                $as = $p + 1;
            }
        }
        $out[$as] = $p - 1;
        return $out;
    }

    /** Tokens::overrideRange: $items = [id, text, source index] (FlatTokens::code / ws) */
    protected static function overrideRange(FlatTokens $t, int $a, int $b, array $items): void
    {
        $span = $b - $a + 1;
        $cnt = \count($items);
        if ($cnt > $span) {
            $ph = [];
            while ($cnt > $span) {
                $ph[] = FlatTokens::code(0, '__PLACEHOLDER__');
                $span++;
            }
            $t->insertAt($b + 1, $ph);
        }
        foreach ($items as $j => $it) {
            $t->k[$a + $j] = $it[0];
            $t->c[$a + $j] = $it[1];
            $t->i[$a + $j] = $it[2];
        }
        for ($j = $a + $cnt; $j <= $b; $j++) {
            $t->clearAt($j);
        }
    }


    /** Token::isCast */
    protected static function isCastKind(int $k): bool
    {
        return $k >= \__McTokId::T_INT_CAST && $k <= \__McTokId::T_VOID_CAST;
    }

    /** `$tokens[$q] = new Token([$id, $text])` */
    protected static function setCode(FlatTokens $t, int $q, int $id, string $text): void
    {
        $t->k[$q] = $id;
        $t->c[$q] = $text;
        $t->i[$q] = FlatTokens::NEW;
    }

    /** a file with a T_STRING `name(` where name's lower-case is one of $names (a cheap Source-level isCandidate) */
    protected static function hasCallOf(Source $s, array $names): bool
    {
        $lens = [];
        foreach ($names as $nm) {
            $lens[\strlen($nm)] = true;
        }
        for ($i = 0; $i + 1 < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING && $s->kind[$i + 1] === 40 && isset($lens[$s->end[$i] - $s->start[$i]])
                && \in_array(\strtolower($s->text($i)), $names, true)) {
                return true;
            }
        }
        return false;
    }

    /** Tokens::clearTokenAndMergeSurroundingWhitespace */
    protected static function clearMerge(FlatTokens $t, int $q): void
    {
        $count = $t->count();
        $t->clearAt($q);
        if ($q === $count - 1) {
            return;
        }
        $next = $t->nonEmptySibling($q, 1);
        if ($next < 0 || !$t->isWs($next)) {
            return;
        }
        $prev = $t->nonEmptySibling($q, -1);
        if ($prev >= 0 && $t->isWs($prev)) {
            $t->c[$prev] = $t->c[$prev] . $t->c[$next];
        } elseif ($t->isEmpty($prev + 1)) {
            $t->k[$prev + 1] = FlatTokens::WS;
            $t->c[$prev + 1] = $t->c[$next];
            $t->i[$prev + 1] = -1;
        }
        $t->clearAt($next);
    }

    /** Tokens::removeLeadingWhitespace / removeTrailingWhitespace ($dir -1 / +1) for the token at $q */
    protected static function removeWs(FlatTokens $t, int $q, int $dir): void
    {
        $w = $t->nonEmptySibling($q, $dir);
        if ($w < 0 || !$t->isWs($w)) {
            return;
        }
        $new = '';
        if ($w - 1 >= 0 && $t->isComment($w - 1) && !\str_starts_with($t->c[$w - 1], '/*')) {
            if (\preg_match('/^(\r\n|\n|\r|\x0b|\x0c)(.*)$/s', $t->c[$w], $m) !== 1 || $m[2] === '') {
                return;
            }
            $new = $m[1];
        }
        if ($new === '') {
            $t->clearAt($w);
        } else {
            $t->c[$w] = $new;
        }
    }

    /** the effective id of the item is $id and it is code */
    protected static function is(FlatTokens $t, int $q, int $id): bool
    {
        return $q >= 0 && $q < $t->count() && $t->i[$q] !== -1 && $t->k[$q] === $id;
    }

    /** Tokens::getNextTokenOfKind for one plain / effective id (-1 when none) */
    protected static function nextOf(FlatTokens $t, int $q, int $id): int
    {
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && $t->k[$j] === $id) {
                return $j;
            }
        }
        return -1;
    }

    /** Token::isWhitespace($chars) for a whitespace item: nothing but those characters (empty counts) */
    protected static function wsOnly(FlatTokens $t, int $q, string $chars): bool
    {
        return $q >= 0 && $q < $t->count() && $t->k[$q] === FlatTokens::WS && \strspn($t->c[$q], $chars) === \strlen($t->c[$q]);
    }

    /** WhitespacesAnalyzer::detectIndent: the indent of the line the token at $index is on (the last whitespace item with a line break before it, after its last line break) */
    protected static function detectIndent(FlatTokens $t, int $index): string
    {
        while (true) {
            $w = -1;
            for ($j = $index - 1; $j >= 0; --$j) {
                if ($t->k[$j] === FlatTokens::WS && $t->c[$j] !== '') {
                    $w = $j;
                    break;
                }
            }
            if ($w < 0) {
                return '';
            }
            if (\str_contains($t->c[$w], "\n")) {
                break;
            }
            if ($w >= 1) {
                $p = $w - 1;
                $c = $t->content($p);
                $open = $t->i[$p] !== -1 && $t->k[$p] === \__McTokId::T_OPEN_TAG;
                $comment = $t->k[$p] === FlatTokens::COMMENT && !Trivia::isDoc($c);
                if (($open || $comment) && \substr($c, -1) === "\n") {
                    break;
                }
            }
            $index = $w;
        }
        $parts = \explode("\n", $t->c[$w]);
        return $parts[\count($parts) - 1];
    }
}
