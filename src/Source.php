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
    /** @var string[] */
    public array $gapBefore = [];
    public string $gapAfterLast = '';
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
        $texts = $t->texts;
        $offs = $t->offs;
        $kind = [];
        $start = [];
        $end = [];
        $gaps = [];
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
            $end[] = $a + \strlen($texts[$j]);
            $gaps[] = $a > $prevEnd ? \substr($code, $prevEnd, $a - $prevEnd) : '';
            $prevEnd = $a + \strlen($texts[$j]);
            $k++;
        }
        $s->kind = $kind;
        $s->start = $start;
        $s->end = $end;
        $s->gapBefore = $gaps;
        $s->gapAfterLast = $prevEnd < \strlen($code) ? \substr($code, $prevEnd) : '';
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

    /** Gap before token $i; $i === n is the trailing trivia. */
    public function gap(int $i): string
    {
        return $i === $this->n ? $this->gapAfterLast : $this->gapBefore[$i];
    }

    public function setGap(int $i, string $g): void
    {
        if ($i === $this->n) {
            $this->gapAfterLast = $g;
        } else {
            $this->gapBefore[$i] = $g;
        }
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
        $out .= $this->gapAfterLast;
        return $out;
    }
}
