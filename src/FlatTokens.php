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

    /** Loads code tokens $a..$b and the trivia between them. */
    public function load(Source $s, int $a, int $b): void
    {
        $this->s = $s;
        $this->k = [];
        $this->c = [];
        $this->i = [];
        $this->pos = [];
        for ($t = $a; $t <= $b; $t++) {
            if ($t > $a) {
                $g = $s->gapBefore[$t];
                if ($g !== '') {
                    foreach (Gap::hasComment($g) ? Gap::pieces($g) : [$g] as $p) {
                        $this->k[] = Gap::isSpace($p) ? self::WS : self::COMMENT;
                        $this->c[] = $p;
                        $this->i[] = -1;
                    }
                }
            }
            $this->pos[$t] = \count($this->k);
            $this->k[] = $s->ct[$t] !== 0 ? $s->ct[$t] : $s->kind[$t];
            $this->c[] = '';
            $this->i[] = $t;
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

    /** the window as code: every item's content in order */
    public function render(): string
    {
        $out = '';
        $m = \count($this->k);
        for ($q = 0; $q < $m; $q++) {
            $out .= $this->i[$q] >= 0 ? $this->s->text($this->i[$q]) : $this->c[$q];
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
