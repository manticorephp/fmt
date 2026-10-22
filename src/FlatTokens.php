<?php

declare(strict_types=1);

namespace Fmt;

/**
 * A window of the file as upstream's Tokens sees it: a flat list of code tokens, whitespace tokens and comment tokens (the gap
 * pieces between the code tokens), with upstream's integer indexes, so a fixer's algorithm that inserts, clears and moves
 * trivia can be ported line by line. A cleared item is an empty whitespace item (upstream: Token('') is whitespace, empty).
 * Code tokens keep their Source index; store() writes the trivia between them back as gaps, so the code tokens are never
 * reordered, only the trivia around them.
 */
final class FlatTokens
{
    public const WS = -1;
    public const COMMENT = -2;
    /** source index of a code token an edit rule inserted (its content is in c[]) */
    public const NEW = -2;

    /** @var int[] effective id of an item (custom id or lexer kind for code), WS / COMMENT for trivia */
    public array $k = [];
    /** @var string[] content of a trivia item ('' for code) */
    public array $c = [];
    /** @var int[] Source token index of a code item, -1 for trivia */
    public array $i = [];
    /** @var array<int, int> Source token index => its item index at load time */
    public array $pos = [];
    private ?Source $s = null;
    /** @var int[] set by stretches(): the source index of each code item that could anchor, -1 for the others */
    private array $cand = [];

    /** Loads code tokens $a..$b and the trivia between them ($tail: and the trivia after $b, e.g. the end of the file). */
    public function load(Source $s, int $a, int $b, bool $tail = false): void
    {
        // built in locals and stored once: an append to a property array measured quadratic in the compiled binary
        $this->s = $s;
        $k = [];
        $c = [];
        $ix = [];
        $pos = [];
        $q = 0;
        for ($t = $a; $t <= $b + 1; $t++) {
            if ($t > $a && ($t <= $b || $tail)) {
                $g = $s->gapBefore[$t];
                if ($g === '') {
                    // nothing between the tokens
                } elseif (($s->gapFlags[$t] & Gap::G_COMMENT) === 0) {
                    $k[] = self::WS;
                    $c[] = $g;
                    $ix[] = -1;
                    $q++;
                } else {
                    foreach (Gap::pieces($g) as $p) {
                        $k[] = Gap::isSpace($p) ? self::WS : self::COMMENT;
                        $c[] = $p;
                        $ix[] = -1;
                        $q++;
                    }
                }
            }
            if ($t > $b) {
                break;
            }
            $pos[$t] = $q;
            $k[] = $s->ct[$t] !== 0 ? $s->ct[$t] : $s->kind[$t];
            $c[] = '';
            $ix[] = $t;
            $q++;
        }
        $this->k = $k;
        $this->c = $c;
        $this->i = $ix;
        $this->pos = $pos;
        if (\count($s->wsSplit) > 0) {
            $this->splitGaps($s, $a, $b, $tail);
        }
    }

    /** upstream holds a gap that an edit cleared a code token out of as two adjacent whitespace tokens (Source::$wsSplit): cut the loaded one there */
    private function splitGaps(Source $s, int $a, int $b, bool $tail): void
    {
        $gaps = \array_keys($s->wsSplit);
        \rsort($gaps);
        foreach ($gaps as $g) {
            if ($g <= $a || $g > $b + 1 || ($g > $b && !$tail)) {
                continue;
            }
            $off = $s->wsSplit[$g];
            $end = $g <= $b ? $this->pos[$g] : \count($this->k);
            $st = $end;
            while ($st > 0 && $this->i[$st - 1] === -1) {
                $st--;
            }
            $at = 0;
            for ($q = $st; $q < $end; $q++) {
                $x = $this->c[$q];
                $l = \strlen($x);
                if (($this->k[$q] === self::WS || $this->k[$q] === self::COMMENT) && $off > $at && $off < $at + $l) {
                    \array_splice($this->k, $q, 1, [$this->k[$q], $this->k[$q]]);
                    \array_splice($this->c, $q, 1, [\substr($x, 0, $off - $at), \substr($x, $off - $at)]);
                    \array_splice($this->i, $q, 1, [-1, -1]);
                    foreach ($this->pos as $tk => $pv) {
                        if ($pv > $q) {
                            $this->pos[$tk] = $pv + 1;
                        }
                    }
                    break;
                }
                $at += $l;
            }
        }
    }

    /** Writes the trivia between the code tokens back as the gaps of the code tokens that follow them. */
    public function store(Source $s): void
    {
        $m = \count($this->k);
        $acc = '';
        $first = true;
        for ($q = 0; $q < $m; $q++) {
            if ($this->i[$q] < 0) {
                $acc .= $this->c[$q];
                continue;
            }
            if (!$first) {
                $s->setGap($this->i[$q], $acc);
            }
            $first = false;
            $acc = '';
        }
    }

    public function count(): int
    {
        return \count($this->k);
    }

    public function isWs(int $q): bool
    {
        return $q >= 0 && $q < \count($this->k) && $this->k[$q] === self::WS;
    }

    public function isComment(int $q): bool
    {
        return $q >= 0 && $q < \count($this->k) && $this->k[$q] === self::COMMENT;
    }

    public function isEmpty(int $q): bool
    {
        return $this->isWs($q) && $this->c[$q] === '';
    }

    /** an item is the code token with this effective id */
    public function isId(int $q, int $id): bool
    {
        return $q >= 0 && $q < \count($this->k) && $this->i[$q] !== -1 && $this->k[$q] === $id;
    }

    public function content(int $q): string
    {
        return $this->i[$q] >= 0 && $this->s !== null ? $this->s->text($this->i[$q]) : $this->c[$q];
    }

    /**
     * Records this window (loaded from tokens $a..$b, $tail as in load) as the edits of those tokens, split at ANCHORS: original
     * tokens kept in their original order, each right after its original predecessor and behind its untouched gap with no
     * whitespace split in it. Only the stretches between anchors are edits (none when nothing changed), so the rebuild re-lexes
     * what changed, not the window. The anchors are taken first come; $sorted (a rule that moves whole runs of tokens): they are
     * the heaviest ascending sequence of the runs, so the moved ones are re-lexed and the rest stay. Each edit carries the offsets where two
     * non-empty whitespace items meet (a rule cleared the code between them: upstream never merges them back) or a comment
     * follows a line comment (it would swallow its neighbour when the text is lexed again); $splits false: none (a rule whose
     * upstream rebuilds the range as one token list).
     */
    public function emit(Source $s, int $a, int $b, bool $tail, bool $splits = true, bool $sorted = false): void
    {
        if ($sorted) {
            $this->stretches($s, $a, $b, $tail, $splits, true, null);
            $this->stretches($s, $a, $b, $tail, $splits, false, $this->heaviestChain());
        } else {
            $this->stretches($s, $a, $b, $tail, $splits, false, null);
        }
    }

    /**
     * Records the edits of emit(). $keep null: a candidate anchors when it comes after the last anchor; $keep: which code items
     * anchor. $collect: no edits, only the candidates (cand[]).
     * @param bool[]|null $keep
     */
    private function stretches(Source $s, int $a, int $b, bool $tail, bool $splits, bool $collect, ?array $keep): void
    {
        $m = \count($this->k);
        $cand = [];
        $j = 0;
        $lastA = $a - 1;
        $prevI = $a - 1;
        $prevWs = false;
        $prevLine = false;
        /** text since the last anchor, before the current trivia run */
        $pend = '';
        /** @var int[] $pendSp whitespace splits in pend */
        $pendSp = [];
        $triv = '';
        /** @var int[] $trivSp splits in the current trivia run, relative to its start */
        $trivSp = [];
        for ($q = 0; $q < $m; $q++) {
            $i = $this->i[$q];
            if ($i === -1) {
                $x = $this->c[$q];
                if ($x === '') {
                    continue;
                }
                $ws = $this->k[$q] === self::WS;
                $cm = !$ws && $this->k[$q] === self::COMMENT;
                if ($splits && ($ws && $prevWs || $cm && $prevLine)) {
                    $trivSp[] = \strlen($triv);
                }
                $prevLine = $cm && ($x[0] === '#' ? ($x[1] ?? '') !== '[' : \str_starts_with($x, '//'));
                $prevWs = $ws;
                $triv .= $x;
                continue;
            }
            $ok = $i >= 0 && $i === $prevI + 1 && $trivSp === [] && !isset($s->wsSplit[$i]) && $triv === ($i === $a ? '' : $s->gapBefore[$i]);
            if ($collect) {
                $cand[] = $ok ? $i : -1;
            } elseif ($keep !== null ? $keep[$j] : $ok && $i > $lastA) {
                if ($lastA !== $i - 1) {
                    $s->edit($lastA < $a ? Source::tokAt($a) : Source::gapAt($lastA + 1), Source::gapAt($i), $pend, -1, $pendSp);
                }
                $lastA = $i;
                $pend = '';
                $pendSp = [];
            } else {
                $x = $i >= 0 ? $s->text($i) : $this->c[$q];
                foreach ($trivSp as $sp) {
                    $pendSp[] = \strlen($pend) + $sp;
                }
                $pend .= $triv . $x;
            }
            if ($i >= 0 || $this->c[$q] !== '') {
                $prevWs = false;
                $prevLine = false;
            }
            $prevI = $i >= 0 ? $i : -3;
            $triv = '';
            $trivSp = [];
            $j++;
        }
        if ($collect) {
            $this->cand = $cand;
            return;
        }
        $n = $s->n;
        if ($lastA === $b && $pend === '' && $trivSp === [] && ($tail ? $b === $n - 1 && $triv === $s->gapBefore[$n] && !isset($s->wsSplit[$n]) : $triv === '')) {
            return;
        }
        foreach ($trivSp as $sp) {
            $pendSp[] = \strlen($pend) + $sp;
        }
        $s->edit($lastA < $a ? Source::tokAt($a) : Source::gapAt($lastA + 1), $tail ? Source::gapAt($n) + 1 : Source::tokAt($b) + 1, $pend . $triv, -1, $pendSp);
    }

    /**
     * Which candidates anchor (see stretches): they form chains (consecutive code items, consecutive source indexes); the chains
     * kept are the heaviest ascending sequence of them (most tokens left in place).
     * @return bool[]
     */
    private function heaviestChain(): array
    {
        $cand = $this->cand;
        $nc = \count($cand);
        /** @var int[] $cf */
        $cf = [];
        /** @var int[] $cl */
        $cl = [];
        /** @var int[] $cw */
        $cw = [];
        /** @var int[] $of chain of each code item, -1 = none */
        $of = [];
        $cur = -1;
        for ($j = 0; $j < $nc; $j++) {
            $i = $cand[$j];
            if ($i < 0) {
                $of[] = -1;
                $cur = -1;
                continue;
            }
            if ($cur < 0) {
                $cur = \count($cf);
                $cf[] = $i;
                $cl[] = $i;
                $cw[] = 0;
            }
            $cl[$cur] = $i;
            $cw[$cur]++;
            $of[] = $cur;
        }
        $ch = \count($cf);
        // heaviest ascending sequence: the chains are disjoint ranges of source indexes, so "after y" is cf[y] < cf[x]; a Fenwick
        // tree over the ranks of cf keeps the best total (and its chain) ending below each rank
        $sorted = $cf;
        \sort($sorted);
        $rank = \array_flip($sorted);
        /** @var int[] $fb */
        $fb = \array_fill(0, $ch + 1, 0);
        /** @var int[] $fi */
        $fi = \array_fill(0, $ch + 1, -1);
        /** @var int[] $best */
        $best = [];
        /** @var int[] $from */
        $from = [];
        $top = -1;
        for ($x = 0; $x < $ch; $x++) {
            $r = $rank[$cf[$x]];
            $bv = 0;
            $bp = -1;
            for ($p = $r; $p > 0; $p -= $p & -$p) {
                if ($fb[$p] > $bv) {
                    $bv = $fb[$p];
                    $bp = $fi[$p];
                }
            }
            $v = $bv + $cw[$x];
            $best[] = $v;
            $from[] = $bp;
            for ($p = $r + 1; $p <= $ch; $p += $p & -$p) {
                if ($v > $fb[$p]) {
                    $fb[$p] = $v;
                    $fi[$p] = $x;
                }
            }
            if ($top < 0 || $v > $best[$top]) {
                $top = $x;
            }
        }
        /** @var bool[] $keep */
        $keep = \array_fill(0, $ch, false);
        for ($x = $top; $x >= 0; $x = $from[$x]) {
            $keep[$x] = true;
        }
        $out = [];
        for ($j = 0; $j < $nc; $j++) {
            $out[] = $of[$j] >= 0 && $keep[$of[$j]];
        }
        return $out;
    }

    /** a code item (source or inserted) */
    public function isCode(int $q): bool
    {
        return $this->i[$q] !== -1;
    }

    /** Tokens::getNextMeaningfulToken / getPrevMeaningfulToken: -1 when there is none */
    public function meaningful(int $q, int $dir): int
    {
        $m = \count($this->k);
        for ($j = $q + $dir; $j >= 0 && $j < $m; $j += $dir) {
            if ($this->i[$j] !== -1) {
                return $j;
            }
        }
        return -1;
    }

    /** Tokens::getPrevNonWhitespace / getNextNonWhitespace (a comment counts): -1 when there is none */
    public function nonWhitespace(int $q, int $dir): int
    {
        $m = \count($this->k);
        for ($j = $q + $dir; $j >= 0 && $j < $m; $j += $dir) {
            if ($this->k[$j] !== self::WS) {
                return $j;
            }
        }
        return -1;
    }

    /** Tokens::findBlockEnd for the plain (or same-CT) opener at $q, closer id $close: depth scan, -1 when unbalanced */
    public function blockEnd(int $q, int $close): int
    {
        $open = $this->k[$q];
        $d = 0;
        $m = \count($this->k);
        for ($j = $q; $j < $m; $j++) {
            if ($this->i[$j] === -1) {
                continue;
            }
            $x = $this->k[$j];
            if ($x === $open) {
                $d++;
            } elseif ($x === $close) {
                $d--;
                if ($d === 0) {
                    return $j;
                }
            }
        }
        return -1;
    }

    /** Tokens::findBlockStart for the closer at $q, opener id $open: backward depth scan, -1 when unbalanced */
    public function blockStart(int $q, int $open): int
    {
        $close = $this->k[$q];
        $d = 0;
        for ($j = $q; $j >= 0; $j--) {
            if ($this->i[$j] === -1) {
                continue;
            }
            $x = $this->k[$j];
            if ($x === $close) {
                $d++;
            } elseif ($x === $open) {
                $d--;
                if ($d === 0) {
                    return $j;
                }
            }
        }
        return -1;
    }

    /** a code token an edit inserts (effective id, text) */
    public static function code(int $id, string $text): array
    {
        return [$id, $text, self::NEW];
    }

    public static function ws(string $text): array
    {
        return [self::WS, $text, -1];
    }

    public function clearAt(int $q): void
    {
        $this->k[$q] = self::WS;
        $this->c[$q] = '';
        $this->i[$q] = -1;
    }

    public function setWs(int $q, string $content): void
    {
        $this->c[$q] = $content;
    }

    /** @param array<int, array{0:int,1:string,2:int}> $items [id, content, source index] */
    public function insertAt(int $at, array $items): void
    {
        $k = [];
        $c = [];
        $i = [];
        foreach ($items as $it) {
            $k[] = $it[0];
            $c[] = $it[1];
            $i[] = $it[2];
        }
        \array_splice($this->k, $at, 0, $k);
        \array_splice($this->c, $at, 0, $c);
        \array_splice($this->i, $at, 0, $i);
    }

    /**
     * Tokens::insertSlices: every slice goes in front of the item at its index, all in one rebuild (insertAt costs three array_splice over the whole list per call). The indexes are
     * those of the list before the call.
     * @param array<int, array<int, array{0:int,1:string,2:int}>> $slices
     */
    public function insertSlices(array $slices): void
    {
        if ($slices === []) {
            return;
        }
        /** @var int[] $k */
        $k = [];
        /** @var string[] $c */
        $c = [];
        /** @var int[] $i */
        $i = [];
        for ($q = 0, $n = \count($this->k); $q < $n; $q++) {
            if (isset($slices[$q])) {
                foreach ($slices[$q] as $it) {
                    $k[] = $it[0];
                    $c[] = $it[1];
                    $i[] = $it[2];
                }
            }
            $k[] = $this->k[$q];
            $c[] = $this->c[$q];
            $i[] = $this->i[$q];
        }
        $this->k = $k;
        $this->c = $c;
        $this->i = $i;
    }

    /** @return array{0:int,1:string,2:int} */
    public function item(int $q): array
    {
        return [$this->k[$q], $this->c[$q], $this->i[$q]];
    }

    /** Tokens::getNonEmptySibling; -1 when there is none */
    public function nonEmptySibling(int $q, int $dir): int
    {
        $m = \count($this->k);
        for ($j = $q + $dir; $j >= 0 && $j < $m; $j += $dir) {
            if (!$this->isEmpty($j)) {
                return $j;
            }
        }
        return -1;
    }
}
