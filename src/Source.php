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
    /** @var string[] n + 1 entries: gapBefore[i] precedes token i, gapBefore[n] is the trailing trivia */
    public array $gapBefore = [];
    /** @var int[] Gap::G_* per gap, same indexing as gapBefore */
    public array $gapFlags = [];
    /** @var array<int,string> */
    public array $over = [];
    public bool $dirty = false;

    /** Compiler workaround: the prelude tokenizer is demand-gated on the name PhpToken, not on __McTok. */
    private const TOKENIZER_GATE = \PhpToken::class;

    public static function parse(string $code): Source
    {
        $s = new Source();
        $s->code = $code;
        $t = \__McTok::run($code, 0, 1);
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
        $k = 0;
        for ($j = 0; $j < $count; $j++) {
            $id = $meta[$j] & 4095;
            if ($id === \__McTokId::T_WHITESPACE || $id === \__McTokId::T_COMMENT || $id === \__McTokId::T_DOC_COMMENT) {
                continue;
            }
            $a = $offs[$j];
            $kind[] = $id;
            $start[] = $a;
            $glen = $a - $prevEnd;
            $prevEnd = $j + 1 < $count ? $offs[$j + 1] : $len;
            $end[] = $prevEnd;
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
            $k++;
        }
        $s->kind = $kind;
        $s->start = $start;
        $s->end = $end;
        $tail = $prevEnd < $len ? \substr($code, $prevEnd) : '';
        $gaps[] = $tail;
        $flags[] = Gap::flags($tail);
        $s->gapBefore = $gaps;
        $s->gapFlags = $flags;
        $s->n = $k;
        return $s;
    }

    public function text(int $i): string
    {
        if (isset($this->over[$i])) {
            return $this->over[$i];
        }
        return \substr($this->code, $this->start[$i], $this->end[$i] - $this->start[$i]);
    }

    public function setGap(int $i, string $g): void
    {
        $this->gapBefore[$i] = $g;
        $this->gapFlags[$i] = Gap::flags($g);
        $this->dirty = true;
    }

    public function setText(int $i, string $t): void
    {
        $this->over[$i] = $t;
        $this->dirty = true;
    }

    public function join(): string
    {
        if (!$this->dirty) {
            return $this->code;
        }
        $out = '';
        for ($i = 0; $i < $this->n; $i++) {
            $out .= $this->gapBefore[$i];
            $out .= $this->text($i);
        }
        $out .= $this->gapBefore[$this->n];
        return $out;
    }
}
