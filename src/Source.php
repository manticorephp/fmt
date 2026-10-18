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
