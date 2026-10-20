<?php

declare(strict_types=1);

namespace Fmt;

/**
 * One file, lexed once. Tokens (everything but whitespace/comments) are immutable parallel columns over `code`;
 * trivia lives in gapBefore[i] (the only mutable column, plus gapAfterLast for trailing trivia).
 * Token text is substr(code, start, end), never stored. The sole exception is `over`: a sparse text
 * override for the few tokens whose own bytes carry line endings (open/close tag, heredoc), see LineEnding.
 */
final class Source
{
    public string $code = '';
    public int $n = 0;
    /** @var int[] */
    public array $kind = [];
    /** @var int[] */
    public array $start = [];
    /** @var int[] */
    public array $end = [];
    /** @var int[] custom token kind (CT::*) of token i, 0 = none; kind[] is never changed (see Transform) */
    public array $ct = [];
    /** @var int[] matching bracket/brace/paren of an opener or closer, -1 otherwise (see Transform) */
    public array $pair = [];
    /** @var string[] n + 1 entries: gapBefore[i] precedes token i, gapBefore[n] is the trailing trivia */
    public array $gapBefore = [];
    /** @var int[] Gap::G_* per gap, same indexing as gapBefore */
    public array $gapFlags = [];
    /** @var array<int,string> */
    public array $over = [];
    public bool $dirty = false;
    /**
     * doc pieces whose text no longer looks like a docblock (an emptied `{@inheritDoc}` docblock left as three stars): upstream never re-lexes, so they keep
     * their T_DOC_COMMENT kind for the rest of the run (DocRule::isDocPiece)
     * @var array<int, array<string, bool>> gap => piece texts
     */
    public array $docLike = [];
    /** @var int[] T_NAMESPACE tokens split off a `NAMESPACE\X` name: upstream's transformer rewrites them to lowercase `namespace` in any file it changes */
    public array $relNs = [];
    /** @var int[] gaps rewritten by setGap (may repeat), for join() */
    private array $changed = [];
    /**
     * Set by a rule when upstream would produce code that does not lint (its lint step then leaves the file untouched,
     * reporting an error): the file is left as it is.
     */
    public bool $abort = false;
    /** Set by a rule that may leave `use A\ B;` behind: whether that survives to the end of the run is checked on the final code (upstream lints once, at the end). */
    public bool $lintUse = false;
    /** Set by a rule that met a construct only the edit phase (token insert / delete) can handle: the file is reported and left as it is. */
    public string $unsupported = '';
    /** built on first use (Source::structure); token kinds never change, so it stays valid for the whole gap phase */
    private ?Structure $st = null;

    /** Compiler workaround: the prelude tokenizer is demand-gated on the name PhpToken, not on __McTok. */
    private const TOKENIZER_GATE = \PhpToken::class;

    public static function parse(string $code): Source
    {
        $s = new Source();
        $s->code = $code;
        $t = \__McTok::run($code, 1, 1);
        $count = $t->n;
        $meta = $t->meta;
        $offs = $t->offs;
        // A token ends where the next lexer token starts, so texts[] is never touched.
        // (array_fill + indexed stores measured SLOWER than appends: +0.3 G on corpus-src.)
        $kind = [];
        $start = [];
        $end = [];
        $flags = [];
        $gaps = [];
        $len = \strlen($code);
        $prevEnd = 0;
        $prevId = 0;
        /** @var int[] $asFix T_STRING tokens right after `as` (trait alias), see below */
        $asFix = [];
        for ($j = 0; $j < $count; $j++) {
            $id = $meta[$j] & 4095;
            if ($id === \__McTokId::T_WHITESPACE || $id === \__McTokId::T_COMMENT || $id === \__McTokId::T_DOC_COMMENT) {
                continue;
            }
            $a = $offs[$j];
            if ($id === \__McTokId::T_STRING && $prevId === \__McTokId::T_AS) {
                $asFix[] = \count($kind);
            }
            $prevId = $id;
            $glen = $a - $prevEnd;
            $prevEnd = $j + 1 < $count ? $offs[$j + 1] : $len;
            if ($glen === 0) {
                $gaps[] = '';
                $flags[] = 0;
            } elseif ($glen === 1 && \ord($code[$a - 1]) === 32) {
                $gaps[] = ' ';
                $flags[] = 0;
            } else {
                $g = \substr($code, $a - $glen, $glen);
                $gaps[] = $g;
                $flags[] = Gap::flags($g);
            }
            if ($id >= \__McTokId::T_NAME_FULLY_QUALIFIED && $id <= \__McTokId::T_NAME_QUALIFIED) {
                // NameQualifiedTransformer: T_NAME_* becomes T_NAMESPACE / T_NS_SEPARATOR / T_STRING tokens, extra ones with empty gaps
                $q = $a;
                $first = true;
                if ($id === \__McTokId::T_NAME_RELATIVE) {
                    if (\substr($code, $q, 9) !== 'namespace') {
                        $s->relNs[] = \count($kind);
                    }
                    $kind[] = \__McTokId::T_NAMESPACE;
                    $start[] = $q;
                    $q += 9;
                    $end[] = $q;
                    $first = false;
                }
                while ($q < $prevEnd) {
                    if (!$first) {
                        $gaps[] = '';
                        $flags[] = 0;
                    }
                    $first = false;
                    $start[] = $q;
                    if ($code[$q] === '\\') {
                        $kind[] = \__McTokId::T_NS_SEPARATOR;
                        $q++;
                    } else {
                        $r = \strpos($code, '\\', $q);
                        $q = ($r === false || $r > $prevEnd) ? $prevEnd : $r;
                        $kind[] = \__McTokId::T_STRING;
                    }
                    $end[] = $q;
                }
            } else {
                $kind[] = $id;
                $start[] = $a;
                $end[] = $prevEnd;
            }
        }
        // Compiler workaround: the prelude tokenizer's TOKEN_PARSE emulation turns `as protected;` (a trait alias that only
        // changes visibility) into T_STRING; Zend keeps the modifier keyword.
        foreach ($asFix as $ai) {
            $w = $end[$ai] - $start[$ai];
            if ($w >= 6 && $w <= 9 && $ai + 1 < \count($kind) && $kind[$ai + 1] === 59) {
                $w = \strtolower(\substr($code, $start[$ai], $w));
                if ($w === 'public') {
                    $kind[$ai] = \__McTokId::T_PUBLIC;
                } elseif ($w === 'protected') {
                    $kind[$ai] = \__McTokId::T_PROTECTED;
                } elseif ($w === 'private') {
                    $kind[$ai] = \__McTokId::T_PRIVATE;
                }
            }
        }
        $s->kind = $kind;
        $s->start = $start;
        $s->end = $end;
        $tail = $prevEnd < $len ? \substr($code, $prevEnd) : '';
        $gaps[] = $tail;
        $flags[] = Gap::flags($tail);
        $s->gapBefore = $gaps;
        $s->gapFlags = $flags;
        $s->n = \count($kind);
        Transform::run($s);
        return $s;
    }

    /** @var array<int, bool>|null */
    private ?array $sw = null;

    /** Analyzer::switchColons, computed once per file */
    public function switchColons(): array
    {
        if ($this->sw === null) {
            $this->sw = Analyzer::switchColons($this);
        }
        return $this->sw;
    }

    public function structure(): Structure
    {
        if ($this->st === null) {
            $this->st = Structure::build($this);
        }
        return $this->st;
    }

    public function text(int $i): string
    {
        if (isset($this->over[$i])) {
            return $this->over[$i];
        }
        return \substr($this->code, $this->start[$i], $this->end[$i] - $this->start[$i]);
    }

    /** Index of the last "\n" or "\r" in $t, -1 when none. */
    private static function lastBreak(string $t): int
    {
        $a = \strrpos($t, "\n");
        $b = \strrpos($t, "\r");
        $a = $a === false ? -1 : $a;
        $b = $b === false ? -1 : $b;
        return $a > $b ? $a : $b;
    }

    /**
     * True when only blanks (spaces/tabs) precede token i on its physical line. Reads the CURRENT gaps and texts, so it
     * stays right after a rule rewrote any of them (the Structure columns never read gaps). A comment before the token on
     * its line makes it false, as in upstream where the comment, not a whitespace token, precedes it.
     */
    public function isLineStart(int $i): bool
    {
        if ($i === 0) {
            return true;
        }
        $g = $this->gapBefore[$i];
        $p = self::lastBreak($g);
        if ($p >= 0) {
            $tail = \strlen($g) - $p - 1;
            return \strspn($g, " \t", $p + 1) === $tail;
        }
        if ($g !== '' && \strspn($g, " \t") !== \strlen($g)) {
            return false;
        }
        $pt = $this->text($i - 1);
        $lb = \ord($pt[\strlen($pt) - 1]);
        return $lb === 10 || $lb === 13;
    }

    /**
     * The blanks (spaces/tabs) that start the physical line token i is on, from the CURRENT gaps. When that line begins
     * inside an earlier multi-line token (heredoc, string, inline HTML) it is the indentation of that token's line.
     */
    public function lineIndent(int $i): string
    {
        $j = $i;
        while (true) {
            $g = $this->gapBefore[$j];
            $p = self::lastBreak($g);
            if ($p >= 0) {
                return \substr($g, $p + 1, \strspn($g, " \t", $p + 1));
            }
            if ($j === 0) {
                return \substr($g, 0, \strspn($g, " \t"));
            }
            $pt = $this->text($j - 1);
            $q = self::lastBreak($pt);
            if ($q >= 0) {
                if ($q === \strlen($pt) - 1) {
                    return \substr($g, 0, \strspn($g, " \t"));
                }
                return \substr($pt, $q + 1, \strspn($pt, " \t", $q + 1));
            }
            $j--;
        }
    }

    public function setGap(int $i, string $g): void
    {
        if ($this->gapBefore[$i] === $g) {
            return; // upstream's offsetSet does not mark a token changed when it equals the old one
        }
        $this->gapBefore[$i] = $g;
        $this->gapFlags[$i] = Gap::flags($g);
        $this->dirty = true;
        $this->changed[] = $i;
    }

    /**
     * A rule that turns a token into a token of another kind (switch_case_semicolon_to_colon: `;` -> `:`), as upstream does. The
     * caches built from kinds (Structure, the switch colon set) are dropped; they are rebuilt on the next use.
     */
    public function setKind(int $i, int $k): void
    {
        $this->kind[$i] = $k;
        $this->st = null;
        $this->sw = null;
    }

    public function setText(int $i, string $t): void
    {
        if ($t === $this->text($i)) {
            return; // not a change for upstream either
        }
        $this->over[$i] = $t;
        $this->dirty = true;
    }

    /**
     * The file: the original code with the rewritten gaps / token texts spliced in (each keeps its original byte range,
     * gap i = [end[i-1], start[i]), token i = [start[i], end[i])), so untouched stretches are copied in one piece.
     */
    public function join(): string
    {
        if (!$this->dirty) {
            return $this->code;
        }
        foreach ($this->relNs as $t) {
            $this->over[$t] = 'namespace';
        }
        $keys = [];
        foreach ($this->changed as $g) {
            $keys[] = $g * 2;
        }
        foreach ($this->over as $t => $x) {
            $keys[] = $t * 2 + 1;
        }
        \sort($keys);
        $code = $this->code;
        $out = '';
        $pos = 0;
        $prev = -1;
        foreach ($keys as $key) {
            if ($key === $prev) {
                continue;
            }
            $prev = $key;
            $i = $key >> 1;
            if (($key & 1) === 0) {
                $a = $i > 0 ? $this->end[$i - 1] : 0;
                $b = $i < $this->n ? $this->start[$i] : \strlen($code);
                $rep = $this->gapBefore[$i];
            } else {
                $a = $this->start[$i];
                $b = $this->end[$i];
                $rep = $this->over[$i];
            }
            $out .= \substr($code, $pos, $a - $pos);
            $out .= $rep;
            $pos = $b;
        }
        return $out . \substr($code, $pos);
    }

    /** @var int[] */
    private array $splitAbs = [];

    /** @var Edit[] token edits recorded by an EditRule, applied by rebuild() */
    public array $edits = [];

    /** stream unit of gap i (i === n: the trailing trivia) */
    public static function gapAt(int $i): int
    {
        return $i * 2;
    }

    /** stream unit of token i */
    public static function tokAt(int $i): int
    {
        return $i * 2 + 1;
    }

    /**
     * Records the replacement of stream units [from, to) (whole gaps / tokens, see gapAt / tokAt) by $text. Insertion: from === to.
     * Edits of one rule must not overlap; they may come in any order.
     */
    /** @param int[] $splits */
    public function edit(int $from, int $to, string $text, int $split = -1, array $splits = []): void
    {
        $this->edits[] = new Edit($from, $to, $text, $split, $splits);
    }

    /**
     * gap index => byte offset: upstream holds this gap as TWO adjacent whitespace tokens split there (an edit cleared the code token
     * between them, and upstream never merges them back within a run). Set by rebuild() from Edit::$split; only the rules the oracle
     * showed sensitive to it read it (single_space_around_construct, no_trailing_whitespace, statement_indentation, single_line_after_imports, the phpdoc rules). A rebuild carries it over for every gap no edit replaced.
     * @var array<int, int>
     */
    public array $wsSplit = [];

    private function unit(int $u): string
    {
        return ($u & 1) === 0 ? $this->gapBefore[$u >> 1] : $this->text($u >> 1);
    }

    /** The current file with the recorded edits spliced in. */
    public function render(): string
    {
        foreach ($this->relNs as $t) {
            $this->over[$t] = 'namespace'; // an edit changes the file: upstream's transformer literal shows (see join)
        }
        $ed = $this->edits;
        \usort($ed, static fn (Edit $a, Edit $b): int => $a->from <=> $b->from ?: $a->to <=> $b->to);
        // units rewritten since the parse (gaps, token texts); every other unit is still its byte range of the original code
        $dirty = [];
        foreach ($this->changed as $g) {
            $dirty[] = $g * 2;
        }
        foreach ($this->over as $t => $x) {
            $dirty[] = $t * 2 + 1;
        }
        \sort($dirty);
        $nd = \count($dirty);
        $di = 0;
        $out = '';
        $u = 0;
        $end = $this->n * 2 + 1;
        $this->splitAbs = [];
        $ei = 0;
        $ne = \count($ed);
        while ($u < $end) {
            while ($di < $nd && $dirty[$di] < $u) {
                $di++;
            }
            $stop = $end;
            if ($ei < $ne && $ed[$ei]->from < $stop) {
                $stop = $ed[$ei]->from;
            }
            if ($di < $nd && $dirty[$di] < $stop) {
                $stop = $dirty[$di];
            }
            if ($stop > $u) {
                $a = $this->unitStart($u);
                // a whitespace split of an untouched gap carries over to the re-parsed file (upstream keeps its two tokens)
                foreach ($this->wsSplit as $sg => $off) {
                    if ($sg * 2 >= $u && $sg * 2 < $stop) {
                        $this->splitAbs[] = \strlen($out) + $this->unitStart($sg * 2) - $a + $off;
                    }
                }
                $out .= \substr($this->code, $a, $this->unitStart($stop) - $a);
                $u = $stop;
                continue;
            }
            if ($ei < $ne && $ed[$ei]->from === $u) {
                $e = $ed[$ei++];
                if ($e->split >= 0) {
                    $this->splitAbs[] = \strlen($out) + $e->split;
                }
                foreach ($e->splits as $sp) {
                    $this->splitAbs[] = \strlen($out) + $sp;
                }
                $out .= $e->text;
                if ($e->to > $u) {
                    $u = $e->to;
                }
                continue;
            }
            if (($u & 1) === 0 && isset($this->wsSplit[$u >> 1])) {
                $this->splitAbs[] = \strlen($out) + $this->wsSplit[$u >> 1];
            }
            $out .= $this->unit($u); // a rewritten unit
            $u++;
        }
        return $out;
    }

    /** byte offset in the original code where stream unit $u starts (unit 2n + 1 = the end of the code) */
    private function unitStart(int $u): int
    {
        $i = $u >> 1;
        if (($u & 1) === 1) {
            return $i < $this->n ? $this->start[$i] : \strlen($this->code);
        }
        return $i > 0 ? $this->end[$i - 1] : 0;
    }

    /** After an EditRule: the file re-parsed with its edits applied, or $this when there were none (or they changed nothing). */
    public function rebuild(): Source
    {
        if ($this->edits === []) {
            return $this;
        }
        $code = $this->render();
        $this->edits = [];
        if ($code === $this->join()) {
            return $this;
        }
        $t = self::parse($code);
        foreach ($this->splitAbs as $abs) {
            $g = 0; // the gap that holds byte $abs: end[g - 1] <= abs <= start[g]
            while ($g < $t->n && $t->start[$g] < $abs) {
                $g++;
            }
            $from = $g > 0 ? $t->end[$g - 1] : 0;
            $off = $abs - $from;
            if ($off > 0 && $off < \strlen($t->gapBefore[$g])) {
                $t->wsSplit[$g] = $off;
            }
        }
        $t->dirty = true;
        foreach ($this->docLike as $pieces) {
            foreach ($pieces as $x => $on) {
                $at = \strpos($code, (string) $x);
                if ($at === false || \strpos($code, (string) $x, $at + 1) !== false) {
                    continue; // gone, or ambiguous: re-lexed as the comment it looks like
                }
                $g = 0;
                while ($g < $t->n && $t->start[$g] <= $at) {
                    $g++;
                }
                $t->docLike[$g][(string) $x] = true;
            }
        }
        $t->abort = $this->abort;
        $t->lintUse = $this->lintUse;
        $t->unsupported = $this->unsupported;
        return $t;
    }

    /** join() rebuilt from every gap and token (for tests: the columns alone reproduce the file). */
    public function joinAll(): string
    {
        $out = '';
        for ($i = 0; $i < $this->n; $i++) {
            $out .= $this->gapBefore[$i];
            $out .= $this->text($i);
        }
        $out .= $this->gapBefore[$this->n];
        return $out;
    }
}
