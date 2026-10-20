<?php

declare(strict_types=1);

namespace Fmt;

/**
 * An edit rule ported line by line from upstream on a whole-file FlatTokens (upstream's integer indexes over code, whitespace and comment tokens,
 * `clearAt` leaving an empty whitespace item). `candidate()` is the cheap Source-level test that nothing can change in a file (no load then);
 * `run()` edits the flat list; a changed rendering replaces the whole token range of the file in one span (and the Engine re-parses).
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
        $before = $t->render();
        $this->run($s, $t);
        $after = $t->render();
        if ($after !== $before) {
            $s->edit(Source::tokAt($a), $tail ? Source::gapAt($n) + 1 : Source::tokAt($b) + 1, $after);
        }
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
}
