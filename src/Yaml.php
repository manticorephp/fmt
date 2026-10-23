<?php

declare(strict_types=1);

namespace Fmt;

/**
 * The YAML a formatter config needs, with YAML 1.2 core-schema scalars: block mappings and sequences (a mapping or a sequence
 * may start on the dash's line), flow `[a, b]` / `{k: v}` over several lines, plain / 'single' / "double" quoted scalars
 * (also multi-line, folded), block scalars `|` `>` with chomping and indentation indicators, anchors `&a`, aliases `*a`
 * and merge keys `<<`, `#` comments, a leading `---` and a trailing `...`.
 *
 * Refused with a message naming the feature: tags (`!!str`), directives (`%YAML`), several documents, complex keys (`? `),
 * anchors / aliases on a mapping key, single-pair mappings inside `[...]`, duplicate keys in a mapping.
 * Every error says `YAML line N`.
 */
final class Yaml
{
    private const MAX_DEPTH = 200;

    /** @var string[] */
    private array $ls = [];
    /** @var int[] absolute offset of every line start in $s */
    private array $st = [];
    private string $s = '';
    private int $n = 0;
    private int $p = 0;
    /** flow cursor: an absolute offset in $s */
    private int $o = 0;
    private int $depth = 0;
    /** @var array<string, mixed> */
    private array $anchors = [];

    /** @throws \InvalidArgumentException */
    public static function parse(string $src): mixed
    {
        if (\str_starts_with($src, "\xEF\xBB\xBF")) {
            $src = \substr($src, 3);
        }
        if (\str_starts_with($src, "\xFF\xFE") || \str_starts_with($src, "\xFE\xFF")) {
            throw new \InvalidArgumentException('YAML line 1: UTF-16 input is not supported, save the file as UTF-8');
        }
        $y = new self();
        return $y->run(\str_replace(["\r\n", "\r"], "\n", $src));
    }

    private function run(string $src): mixed
    {
        $this->ls = \explode("\n", $src);
        $this->n = \count($this->ls);
        $seen = false;
        $started = false;
        for ($i = 0; $i < $this->n; $i++) {
            $l = $this->ls[$i];
            if ($l !== '' && $l[0] === '%' && !$seen && !$started) {
                throw $this->err($i, 'directives (%YAML, %TAG) are not supported');
            }
            if (self::marker($l, '---')) {
                if ($seen || $started) {
                    throw $this->err($i, 'multi-document YAML is not supported (a second "---")');
                }
                $rest = \trim(\substr($l, 3));
                if ($rest !== '' && $rest[0] !== '#') {
                    throw $this->err($i, 'content on the "---" line is not supported, start it on the next line');
                }
                $started = true;
                $this->ls[$i] = '';
                continue;
            }
            if (self::marker($l, '...')) {
                for ($j = $i + 1; $j < $this->n; $j++) {
                    $t = \trim($this->ls[$j]);
                    if ($t !== '' && $t[0] !== '#') {
                        throw $this->err($j, 'multi-document YAML is not supported (content after "...")');
                    }
                }
                $this->ls = \array_slice($this->ls, 0, $i);
                $this->n = $i;
                break;
            }
            $t = \trim($l);
            if ($t !== '' && $t[0] !== '#') {
                $seen = true;
            }
        }
        $off = 0;
        foreach ($this->ls as $l) {
            $this->st[] = $off;
            $off += \strlen($l) + 1;
        }
        $this->s = \implode("\n", $this->ls);

        $this->skipBlank();
        if ($this->p >= $this->n) {
            return null;
        }
        $v = $this->value(-1, $this->p, $this->indent($this->p), true, false);
        $this->skipBlank();
        if ($this->p < $this->n) {
            throw $this->err($this->p, 'unexpected "' . self::clip(\trim($this->ls[$this->p])) . '" (bad indentation, or a second top-level node)');
        }
        return $v;
    }

    private static function marker(string $l, string $m): bool
    {
        return \substr($l, 0, 3) === $m && (\strlen($l) === 3 || $l[3] === ' ' || $l[3] === "\t");
    }

    private static function clip(string $s): string
    {
        return \strlen($s) > 40 ? \substr($s, 0, 40) . '...' : $s;
    }

    private function err(int $line0, string $m): \InvalidArgumentException
    {
        return new \InvalidArgumentException('YAML line ' . ($line0 + 1) . ': ' . $m);
    }

    private function errAt(int $off, string $m): \InvalidArgumentException
    {
        return $this->err($this->lineOf($off), $m);
    }

    private function lineOf(int $off): int
    {
        $l = 0;
        while ($l + 1 < $this->n && $this->st[$l + 1] <= $off) {
            $l++;
        }
        return $l;
    }

    private function skipBlank(): void
    {
        while ($this->p < $this->n) {
            $t = \ltrim($this->ls[$this->p], " \t");
            if ($t !== '' && $t[0] !== '#') {
                return;
            }
            $this->p++;
        }
    }

    private function indent(int $i): int
    {
        $l = $this->ls[$i];
        $sp = \strspn($l, ' ');
        if ($sp < \strlen($l) && $l[$sp] === "\t") {
            throw $this->err($i, 'a tab character is used for indentation (YAML indents with spaces only)');
        }
        return $sp;
    }

    /** the text without a trailing ` # comment`, right-trimmed */
    private static function cut(string $t): string
    {
        if ($t !== '' && $t[0] === '#') {
            return '';
        }
        $end = \strlen($t);
        $a = \strpos($t, ' #');
        if ($a !== false && $a < $end) {
            $end = $a;
        }
        $b = \strpos($t, "\t#");
        if ($b !== false && $b < $end) {
            $end = $b;
        }
        return \rtrim(\substr($t, 0, $end), " \t");
    }

    private static function isDash(string $t): bool
    {
        return $t !== '' && $t[0] === '-' && (\strlen($t) === 1 || $t[1] === ' ' || $t[1] === "\t");
    }

    private function reg(string $anchor, mixed $v): mixed
    {
        if ($anchor !== '') {
            $this->anchors[$anchor] = $v;
        }
        return $v;
    }

    /**
     * The node whose text starts at column $col of line $line (the rest of that line, then the lines below it).
     * $parent is the indentation of the construct that owns the node: a child must be indented deeper.
     * $nested: a block mapping / sequence may start right here (own line, or after a dash), not after `key:`.
     * $seqAtParent: a sequence may sit at the owner's own indentation (`key:` then `- a` below it).
     */
    private function value(int $parent, int $line, int $col, bool $nested, bool $seqAtParent): mixed
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw $this->err($line, 'nesting is deeper than ' . self::MAX_DEPTH . ' levels');
        }
        $v = $this->value1($parent, $line, $col, $nested, $seqAtParent);
        $this->depth--;
        return $v;
    }

    private function value1(int $parent, int $line, int $col, bool $nested, bool $seqAtParent): mixed
    {
        $this->p = $line;
        $t = \substr($this->ls[$line], $col);
        $anchor = '';
        while ($t !== '' && ($t[0] === '&' || $t[0] === '*' || $t[0] === '!')) {
            if ($t[0] === '!') {
                throw $this->err($line, 'tags (!!str, !Tag) are not supported');
            }
            $len = 1 + \strcspn($t, " \t,[]{}", 1);
            $name = \substr($t, 1, $len - 1);
            if ($name === '') {
                throw $this->err($line, 'an anchor or alias needs a name');
            }
            $after = \ltrim(\substr($t, $len), " \t");
            if ($t[0] === '*') {
                if ($anchor !== '') {
                    throw $this->err($line, 'an alias cannot carry an anchor');
                }
                $rest = self::cut($after);
                if ($rest !== '') {
                    throw $this->err($line, $rest[0] === ':' ? 'an alias as a mapping key is not supported' : 'unexpected "' . self::clip($rest) . '" after the alias *' . $name);
                }
                if (!\array_key_exists($name, $this->anchors)) {
                    throw $this->err($line, 'unknown alias *' . $name . ' (an anchor must be defined earlier in the file; quote the text if it is not an alias, e.g. \'*.php\')');
                }
                $this->p = $line + 1;
                return $this->anchors[$name];
            }
            $anchor = $name;
            $col += \strlen($t) - \strlen($after);
            $t = $after;
        }

        $cut = self::cut($t);
        if ($cut === '') {
            $this->p = $line + 1;
            $this->skipBlank();
            $v = null;
            if ($this->p < $this->n) {
                $ind = $this->indent($this->p);
                if ($ind > $parent) {
                    $v = $this->value($parent, $this->p, $ind, true, false);
                } elseif ($seqAtParent && $ind === $parent && self::isDash(\substr($this->ls[$this->p], $ind))) {
                    $v = $this->seq($parent);
                }
            }
            return $this->reg($anchor, $v);
        }

        $c = $t[0];
        if (self::isDash($t)) {
            if (!$nested) {
                throw $this->err($line, 'a block sequence cannot start on the line of a mapping key (put it on the next line)');
            }
            return $this->reg($anchor, $this->seq($col));
        }
        if ($c === '?' && (\strlen($t) === 1 || $t[1] === ' ' || $t[1] === "\t")) {
            throw $this->err($line, 'complex mapping keys ("? ") are not supported, write "key: value"');
        }
        if ($c === '|' || $c === '>') {
            return $this->reg($anchor, $this->blockScalar($parent, $line, $cut));
        }
        if ($c === '[' || $c === '{') {
            $this->o = $this->st[$line] + $col;
            $v = $this->flow();
            $this->endLine($line);
            return $this->reg($anchor, $v);
        }
        if ($c === '@' || $c === '`') {
            throw $this->err($line, 'the character "' . $c . '" is reserved in YAML, quote the text');
        }
        $kl = $this->keyLine($t, $line);
        if ($kl !== null) {
            if (!$nested) {
                throw $this->err($line, 'a mapping value (": ") is not allowed here: a nested mapping goes on the next line, indented; quote the text if the colon belongs to it');
            }
            if ($anchor !== '') {
                throw $this->err($line, 'an anchor on the first key of a mapping is not supported, put the anchor on its own line above the mapping');
            }
            return $this->map($col);
        }
        if ($c === '"' || $c === "'") {
            $this->o = $this->st[$line] + $col;
            $v = $this->quoted($this->o);
            $this->endLine($line);
            return $this->reg($anchor, $v);
        }
        return $this->reg($anchor, $this->plain($parent, $line, $cut));
    }

    /** a plain scalar that starts on $line (text already cut of its comment), with its continuation lines folded in */
    private function plain(int $parent, int $line, string $text): mixed
    {
        $this->noColon($line, $text);
        $last = $line;
        $blanks = 0;
        $multi = false;
        for ($q = $line + 1; $q < $this->n; $q++) {
            $raw = $this->ls[$q];
            $tt = \ltrim($raw, " \t");
            if ($tt === '') {
                $blanks++;
                continue;
            }
            if ($tt[0] === '#') {
                break;
            }
            if ($this->indent($q) <= $parent) {
                break;
            }
            $piece = self::cut($tt);
            $this->noColon($q, $piece);
            $text .= ($blanks > 0 ? \str_repeat("\n", $blanks) : ' ') . $piece;
            $blanks = 0;
            $last = $q;
            $multi = true;
        }
        $this->p = $last + 1;
        return $multi ? $text : self::resolve($text);
    }

    private function noColon(int $line, string $text): void
    {
        if (\preg_match('/:([ \t]|$)/', $text) === 1) {
            throw $this->err($line, 'a mapping value (": ") is not allowed here: indent the nested mapping on its own lines, or quote the text if the colon belongs to it');
        }
    }

    /**
     * `key: rest` at the start of $t: [key, offset of the text after the colon, quoted], null when $t is no mapping entry
     * @return array{0: string, 1: int, 2: bool}|null
     */
    private function keyLine(string $t, int $line): ?array
    {
        if ($t === '') {
            return null;
        }
        $c = $t[0];
        $n = \strlen($t);
        if ($c === '"' || $c === "'") {
            $e = self::quoteEnd($t, 0);
            if ($e < 0) {
                return null;
            }
            $i = $e + 1;
            while ($i < $n && ($t[$i] === ' ' || $t[$i] === "\t")) {
                $i++;
            }
            if ($i < $n && $t[$i] === ':' && ($i + 1 === $n || $t[$i + 1] === ' ' || $t[$i + 1] === "\t")) {
                return [$this->decode(\substr($t, 0, $e + 1), $line), $i + 1, true];
            }
            return null;
        }
        if ($c === '[' || $c === '{' || $c === '|' || $c === '>') {
            return null;
        }
        for ($i = 0; $i < $n; $i++) {
            $ch = $t[$i];
            if ($ch === ':' && ($i + 1 === $n || $t[$i + 1] === ' ' || $t[$i + 1] === "\t")) {
                $key = \rtrim(\substr($t, 0, $i), " \t");
                if ($key === '') {
                    throw $this->err($line, 'an empty mapping key is not supported');
                }
                if ($key[0] === '&' || $key[0] === '*' || $key[0] === '!') {
                    throw $this->err($line, 'anchors, aliases and tags on a mapping key are not supported');
                }
                return [$key, $i + 1, false];
            }
            if ($ch === '#' && $i > 0 && ($t[$i - 1] === ' ' || $t[$i - 1] === "\t")) {
                return null;
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function map(int $col): array
    {
        /** @var array<string, mixed> $out */
        $out = [];
        /** @var array<string, int> $first */
        $first = [];
        $merged = false;
        while (true) {
            $line = $this->p;
            $text = \substr($this->ls[$line], $col);
            $kl = $this->keyLine($text, $line);
            if ($kl === null) {
                if (self::isDash($text)) {
                    throw $this->err($line, 'a sequence entry ("- ") cannot follow a mapping entry at this indentation');
                }
                throw $this->err($line, 'expected "key: value" but found "' . self::clip(\trim($text)) . '"');
            }
            $key = $kl[0];
            $vcol = $col + $kl[1];
            $ln = \strlen($this->ls[$line]);
            while ($vcol < $ln && ($this->ls[$line][$vcol] === ' ' || $this->ls[$line][$vcol] === "\t")) {
                $vcol++;
            }
            $v = $this->value($col, $line, $vcol, false, true);
            if ($key === '<<' && !$kl[2]) {
                if ($merged) {
                    throw $this->err($line, 'duplicate mapping key "<<"');
                }
                $merged = true;
                foreach ($this->mergeSources($v, $line) as $src) {
                    foreach ($src as $mk => $mv) {
                        $mk = (string) $mk;
                        if (!isset($first[$mk]) && !\array_key_exists($mk, $out)) {
                            $out[$mk] = $mv;
                        }
                    }
                }
            } else {
                if (isset($first[$key])) {
                    throw $this->err($line, 'duplicate mapping key "' . $key . '" (first on line ' . $first[$key] . '); YAML keys are unique');
                }
                $first[$key] = $line + 1;
                $out[$key] = $v;
            }
            $this->skipBlank();
            if ($this->p >= $this->n) {
                break;
            }
            $ind = $this->indent($this->p);
            if ($ind < $col) {
                break;
            }
            if ($ind > $col) {
                throw $this->err($this->p, 'bad indentation: ' . $ind . ' spaces match no enclosing level (the entries above are at ' . $col . ')');
            }
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mergeSources(mixed $v, int $line): array
    {
        if (!\is_array($v)) {
            throw $this->err($line, 'the merge key "<<" needs a mapping, an alias to one, or a list of them');
        }
        $srcs = ($v !== [] && \array_is_list($v)) ? $v : [$v];
        /** @var array<int, array<string, mixed>> $out */
        $out = [];
        foreach ($srcs as $m) {
            if (!\is_array($m)) {
                throw $this->err($line, 'the merge key "<<" needs a mapping, an alias to one, or a list of them');
            }
            $out[] = $m;
        }
        return $out;
    }

    /** @return mixed[] */
    private function seq(int $col): array
    {
        /** @var mixed[] $out */
        $out = [];
        while (true) {
            $line = $this->p;
            $l = $this->ls[$line];
            $ln = \strlen($l);
            $vcol = $col + 1;
            while ($vcol < $ln && ($l[$vcol] === ' ' || $l[$vcol] === "\t")) {
                $vcol++;
            }
            $out[] = $this->value($col, $line, $vcol, true, false);
            $this->skipBlank();
            if ($this->p >= $this->n) {
                break;
            }
            $ind = $this->indent($this->p);
            if ($ind > $col) {
                throw $this->err($this->p, 'bad indentation: ' . $ind . ' spaces match no enclosing level (the sequence entries above are at ' . $col . ')');
            }
            if ($ind < $col || !self::isDash(\substr($this->ls[$this->p], $col))) {
                break;
            }
        }
        return $out;
    }

    /** after a quoted / flow value ended at $this->o: only a comment may follow on that line */
    private function endLine(int $from): void
    {
        $e = $this->o;
        $l = $from;
        while ($l + 1 < $this->n && $this->st[$l + 1] <= $e) {
            $l++;
        }
        $lineEnd = $l + 1 < $this->n ? $this->st[$l + 1] - 1 : \strlen($this->s);
        $rest = \substr($this->s, $e, $lineEnd - $e);
        if ($rest !== '') {
            if ($rest[0] !== ' ' && $rest[0] !== "\t" && $rest[0] !== ':') {
                throw $this->err($l, 'unexpected "' . self::clip($rest) . '" after the value');
            }
            $r = \ltrim($rest, " \t");
            if ($r !== '' && $r[0] === ':') {
                throw $this->err($l, 'a mapping value (": ") is not allowed here (a flow collection cannot be a key; quote the text if the colon belongs to it)');
            }
            if ($r !== '' && $r[0] !== '#') {
                throw $this->err($l, 'unexpected "' . self::clip($r) . '" after the value');
            }
        }
        $this->p = $l + 1;
    }

    private function blockScalar(int $parent, int $line, string $hdr): string
    {
        $folded = $hdr[0] === '>';
        $chomp = '';
        $d = 0;
        $n = \strlen($hdr);
        $i = 1;
        while ($i < $n && \strpos('+-0123456789', $hdr[$i]) !== false) {
            $ch = $hdr[$i];
            if ($ch === '+' || $ch === '-') {
                if ($chomp !== '') {
                    throw $this->err($line, 'invalid block scalar header "' . $hdr . '" (two chomping indicators)');
                }
                $chomp = $ch;
            } else {
                if ($d > 0 || $ch === '0') {
                    throw $this->err($line, 'invalid block scalar header "' . $hdr . '" (the indentation indicator is a digit 1-9, once)');
                }
                $d = (int) $ch;
            }
            $i++;
        }
        $rest = \ltrim(\substr($hdr, $i), " \t");
        if ($rest !== '' && $rest[0] !== '#') {
            throw $this->err($line, 'invalid block scalar header "' . self::clip($hdr) . '" (after | or > only - + and one digit may follow, then a comment)');
        }
        $ci = $d > 0 ? $parent + $d : -1;
        $j = $line + 1;
        if ($ci < 0) {
            for ($k = $j; $k < $this->n; $k++) {
                if (\trim($this->ls[$k], " \t") !== '') {
                    $ci = \strspn($this->ls[$k], ' ');
                    break;
                }
            }
            if ($ci <= $parent) {
                $ci = $parent + 1;
            }
        }
        /** @var string[] $lines */
        $lines = [];
        for ($k = $j; $k < $this->n; $k++) {
            $raw = $this->ls[$k];
            if (\trim($raw, " \t") === '') {
                $lines[] = \strlen($raw) > $ci ? \substr($raw, $ci) : '';
                continue;
            }
            if (\strspn($raw, ' ') < $ci) {
                break;
            }
            $lines[] = \substr($raw, $ci);
        }
        $this->p = $j + \count($lines);
        $trail = 0;
        while ($lines !== [] && $lines[\count($lines) - 1] === '') {
            \array_pop($lines);
            $trail++;
        }
        if ($lines === []) {
            return $chomp === '+' ? \str_repeat("\n", $trail) : '';
        }
        if ($folded) {
            $text = '';
            $blank = 0;
            $prevMore = false;
            $have = false;
            foreach ($lines as $l) {
                if ($l === '') {
                    $blank++;
                    continue;
                }
                $more = $l[0] === ' ' || $l[0] === "\t";
                if (!$have) {
                    $text = \str_repeat("\n", $blank) . $l;
                } elseif ($prevMore || $more) {
                    $text .= "\n" . \str_repeat("\n", $blank) . $l;
                } elseif ($blank === 0) {
                    $text .= ' ' . $l;
                } else {
                    $text .= \str_repeat("\n", $blank) . $l;
                }
                $have = true;
                $prevMore = $more;
                $blank = 0;
            }
        } else {
            $text = \implode("\n", $lines);
        }
        if ($chomp === '-') {
            return $text;
        }
        return $chomp === '+' ? $text . "\n" . \str_repeat("\n", $trail) : $text . "\n";
    }

    // ---- flow collections and quoted scalars, read straight from the source text ----

    private function flowWs(): void
    {
        $len = \strlen($this->s);
        while ($this->o < $len) {
            $ch = $this->s[$this->o];
            if ($ch === ' ' || $ch === "\t" || $ch === "\n") {
                $this->o++;
            } elseif ($ch === '#' && ($this->o === 0 || \strpos(" \t\n", $this->s[$this->o - 1]) !== false)) {
                while ($this->o < $len && $this->s[$this->o] !== "\n") {
                    $this->o++;
                }
            } else {
                return;
            }
        }
    }

    private function flow(): mixed
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw $this->errAt($this->o, 'nesting is deeper than ' . self::MAX_DEPTH . ' levels');
        }
        $v = $this->flow1();
        $this->depth--;
        return $v;
    }

    private function flow1(): mixed
    {
        $len = \strlen($this->s);
        $this->flowWs();
        $anchor = '';
        while ($this->o < $len && ($this->s[$this->o] === '&' || $this->s[$this->o] === '*' || $this->s[$this->o] === '!')) {
            $c = $this->s[$this->o];
            if ($c === '!') {
                throw $this->errAt($this->o, 'tags (!!str, !Tag) are not supported');
            }
            $w = \strcspn($this->s, " \t\n,[]{}", $this->o + 1);
            $name = \substr($this->s, $this->o + 1, $w);
            if ($name === '') {
                throw $this->errAt($this->o, 'an anchor or alias needs a name');
            }
            $this->o += 1 + $w;
            if ($c === '*') {
                if (!\array_key_exists($name, $this->anchors)) {
                    throw $this->errAt($this->o, 'unknown alias *' . $name . ' (an anchor must be defined earlier in the file; quote the text if it is not an alias)');
                }
                return $this->anchors[$name];
            }
            $anchor = $name;
            $this->flowWs();
        }
        if ($this->o >= $len) {
            throw $this->errAt($len - 1, 'the flow collection is not closed');
        }
        $c = $this->s[$this->o];
        if ($c === '[') {
            $open = $this->o;
            $this->o++;
            /** @var mixed[] $out */
            $out = [];
            while (true) {
                $this->flowWs();
                if ($this->o >= $len) {
                    throw $this->errAt($open, 'the "[" opened here is never closed');
                }
                if ($this->s[$this->o] === ']') {
                    $this->o++;
                    break;
                }
                $out[] = $this->flow();
                $this->flowWs();
                if ($this->o >= $len) {
                    throw $this->errAt($open, 'the "[" opened here is never closed');
                }
                $d = $this->s[$this->o];
                if ($d === ':') {
                    throw $this->errAt($this->o, 'a single-pair mapping inside [...] is not supported, use {key: value}');
                }
                if ($d === ',') {
                    $this->o++;
                } elseif ($d !== ']') {
                    throw $this->errAt($this->o, 'expected "," or "]" but found "' . $d . '"');
                }
            }
            return $this->reg($anchor, $out);
        }
        if ($c === '{') {
            $open = $this->o;
            $this->o++;
            /** @var array<string, mixed> $out */
            $out = [];
            /** @var array<string, int> $first */
            $first = [];
            $merged = false;
            while (true) {
                $this->flowWs();
                if ($this->o >= $len) {
                    throw $this->errAt($open, 'the "{" opened here is never closed');
                }
                if ($this->s[$this->o] === '}') {
                    $this->o++;
                    break;
                }
                $kline = $this->lineOf($this->o);
                $kq = $this->s[$this->o] === '"' || $this->s[$this->o] === "'";
                if ($kq) {
                    $key = $this->quoted($this->o);
                } else {
                    if (\strpos('[{,}]', $this->s[$this->o]) !== false) {
                        throw $this->errAt($this->o, 'expected a key but found "' . $this->s[$this->o] . '" (flow collections as keys are not supported)');
                    }
                    $key = (string) $this->flowPlain(true);
                    if ($key === '') {
                        throw $this->errAt($this->o, 'an empty mapping key is not supported');
                    }
                }
                $this->flowWs();
                $v = null;
                if ($this->o < $len && $this->s[$this->o] === ':') {
                    $this->o++;
                    $this->flowWs();
                    if ($this->o >= $len) {
                        throw $this->errAt($open, 'the "{" opened here is never closed');
                    }
                    if ($this->s[$this->o] !== ',' && $this->s[$this->o] !== '}') {
                        $v = $this->flow();
                    }
                }
                if ($key === '<<' && !$kq) {
                    if ($merged) {
                        throw $this->err($kline, 'duplicate mapping key "<<"');
                    }
                    $merged = true;
                    foreach ($this->mergeSources($v, $kline) as $src) {
                        foreach ($src as $mk => $mv) {
                            $mk = (string) $mk;
                            if (!isset($first[$mk]) && !\array_key_exists($mk, $out)) {
                                $out[$mk] = $mv;
                            }
                        }
                    }
                } else {
                    if (isset($first[$key])) {
                        throw $this->err($kline, 'duplicate mapping key "' . $key . '" (first on line ' . $first[$key] . '); YAML keys are unique');
                    }
                    $first[$key] = $kline + 1;
                    $out[$key] = $v;
                }
                $this->flowWs();
                if ($this->o >= $len) {
                    throw $this->errAt($open, 'the "{" opened here is never closed');
                }
                $d = $this->s[$this->o];
                if ($d === ',') {
                    $this->o++;
                } elseif ($d !== '}') {
                    throw $this->errAt($this->o, 'expected "," or "}" but found "' . $d . '"');
                }
            }
            return $this->reg($anchor, $out);
        }
        if ($c === '"' || $c === "'") {
            return $this->reg($anchor, $this->quoted($this->o));
        }
        if ($c === '|' || $c === '>') {
            throw $this->errAt($this->o, 'a block scalar (| or >) cannot sit inside [...] or {...}');
        }
        return $this->reg($anchor, $this->flowPlain(false));
    }

    /** a plain scalar inside a flow collection, up to `, ] } [ {`, `: `, ` #`; a line break folds into a space */
    private function flowPlain(bool $isKey): mixed
    {
        $len = \strlen($this->s);
        $buf = '';
        $seg = $this->o;
        $st = $this->o;
        while ($this->o < $len) {
            $ch = $this->s[$this->o];
            if (\strpos(',[]{}', $ch) !== false) {
                break;
            }
            if ($ch === ':' && ($this->o + 1 >= $len || \strpos(" \t\n,[]{}", $this->s[$this->o + 1]) !== false)) {
                break;
            }
            if ($ch === '#' && $this->o > $st && \strpos(" \t\n", $this->s[$this->o - 1]) !== false) {
                break;
            }
            if ($ch === "\n") {
                $j = $this->o;
                while ($j < $len && \strpos(" \t\n", $this->s[$j]) !== false) {
                    $j++;
                }
                if ($j >= $len || \strpos(',]}#:', $this->s[$j]) !== false) {
                    break;
                }
                $buf .= \rtrim(\substr($this->s, $seg, $this->o - $seg), " \t") . ' ';
                $this->o = $j;
                $seg = $j;
                continue;
            }
            $this->o++;
        }
        $text = \trim($buf . \substr($this->s, $seg, $this->o - $seg), " \t\n");
        if ($isKey) {
            return $text;
        }
        if ($text === '') {
            throw $this->errAt($this->o, 'a value is missing (an empty entry in a flow collection)');
        }
        return self::resolve($text);
    }

    /** index of the quote that closes the one at $from, -1 when there is none */
    private static function quoteEnd(string $t, int $from): int
    {
        $q = $t[$from];
        $n = \strlen($t);
        for ($i = $from + 1; $i < $n; $i++) {
            if ($q === '"' && $t[$i] === '\\') {
                $i++;
            } elseif ($t[$i] === $q) {
                if ($q === "'" && $i + 1 < $n && $t[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                return $i;
            }
        }
        return -1;
    }

    /** the quoted scalar at absolute offset $off; leaves $this->o after the closing quote */
    private function quoted(int $off): string
    {
        $line = $this->lineOf($off);
        $e = self::quoteEnd($this->s, $off);
        if ($e < 0) {
            throw $this->err($line, 'the quoted scalar opened here is never closed');
        }
        $this->o = $e + 1;
        return $this->decode(\substr($this->s, $off, $e - $off + 1), $line);
    }

    /** a quoted scalar with its quotes: escapes, '' and line folding */
    private function decode(string $raw, int $line): string
    {
        $q = $raw[0];
        $in = \substr($raw, 1, -1);
        if ($q === "'" && \strpos($in, "\n") === false) {
            return \str_replace("''", "'", $in);
        }
        if ($q === '"' && \strpos($in, '\\') === false && \strpos($in, "\n") === false) {
            return $in;
        }
        $out = '';
        $keep = 0;
        $n = \strlen($in);
        for ($i = 0; $i < $n; $i++) {
            $c = $in[$i];
            if ($c === "\n") {
                while (\strlen($out) > $keep && ($out[\strlen($out) - 1] === ' ' || $out[\strlen($out) - 1] === "\t")) {
                    $out = \substr($out, 0, -1);
                }
                $nl = 1;
                $i++;
                while (true) {
                    $j = $i;
                    while ($j < $n && ($in[$j] === ' ' || $in[$j] === "\t")) {
                        $j++;
                    }
                    if ($j < $n && $in[$j] === "\n") {
                        $nl++;
                        $i = $j + 1;
                        continue;
                    }
                    $i = $j;
                    break;
                }
                $out .= $nl === 1 ? ' ' : \str_repeat("\n", $nl - 1);
                $i--;
                continue;
            }
            if ($q === "'") {
                if ($c === "'" && $i + 1 < $n && $in[$i + 1] === "'") {
                    $i++;
                }
                $out .= $c;
                continue;
            }
            if ($c !== '\\') {
                $out .= $c;
                continue;
            }
            $i++;
            if ($i >= $n) {
                throw $this->err($line, 'a backslash at the end of a double-quoted scalar');
            }
            $e = $in[$i];
            if ($e === "\n") {
                $i++;
                while ($i < $n && ($in[$i] === ' ' || $in[$i] === "\t")) {
                    $i++;
                }
                $i--;
                continue;
            }
            if ($e === 'x' || $e === 'u' || $e === 'U') {
                $w = $e === 'x' ? 2 : ($e === 'u' ? 4 : 8);
                $hex = \substr($in, $i + 1, $w);
                if (\strlen($hex) !== $w || !\ctype_xdigit($hex)) {
                    throw $this->err($line, 'bad \\' . $e . ' escape in a double-quoted scalar (needs ' . $w . ' hex digits)');
                }
                $cp = (int) \hexdec($hex);
                $i += $w;
                if ($cp >= 0xD800 && $cp <= 0xDBFF && $e === 'u' && \substr($in, $i + 1, 2) === '\\u') {
                    $lo = \substr($in, $i + 3, 4);
                    if (\strlen($lo) === 4 && \ctype_xdigit($lo) && \hexdec($lo) >= 0xDC00 && \hexdec($lo) <= 0xDFFF) {
                        $cp = 0x10000 + (($cp - 0xD800) << 10) + ((int) \hexdec($lo) - 0xDC00);
                        $i += 6;
                    }
                }
                if (($cp >= 0xD800 && $cp <= 0xDFFF) || $cp > 0x10FFFF) {
                    throw $this->err($line, 'the escape \\' . $e . $hex . ' is not a Unicode scalar value');
                }
                $out .= self::utf8($cp);
                $keep = \strlen($out);
                continue;
            }
            $m = self::simpleEscape($e);
            if ($m === null) {
                throw $this->err($line, 'unknown escape \\' . $e . ' in a double-quoted scalar (use single quotes for text with backslashes, e.g. a Windows path)');
            }
            $out .= $m;
            $keep = \strlen($out);
        }
        return $out;
    }

    private static function simpleEscape(string $e): ?string
    {
        switch ($e) {
            case '0': return "\0";
            case 'a': return "\x07";
            case 'b': return "\x08";
            case 't': return "\t";
            case "\t": return "\t";
            case 'n': return "\n";
            case 'v': return "\x0B";
            case 'f': return "\x0C";
            case 'r': return "\r";
            case 'e': return "\x1B";
            case ' ': return ' ';
            case '"': return '"';
            case '/': return '/';
            case '\\': return '\\';
            case 'N': return "\xC2\x85";
            case '_': return "\xC2\xA0";
            case 'L': return "\xE2\x80\xA8";
            case 'P': return "\xE2\x80\xA9";
        }
        return null;
    }

    private static function utf8(int $cp): string
    {
        if ($cp < 0x80) {
            return \chr($cp);
        }
        if ($cp < 0x800) {
            return \chr(0xC0 | ($cp >> 6)) . \chr(0x80 | ($cp & 0x3F));
        }
        if ($cp < 0x10000) {
            return \chr(0xE0 | ($cp >> 12)) . \chr(0x80 | (($cp >> 6) & 0x3F)) . \chr(0x80 | ($cp & 0x3F));
        }
        return \chr(0xF0 | ($cp >> 18)) . \chr(0x80 | (($cp >> 12) & 0x3F)) . \chr(0x80 | (($cp >> 6) & 0x3F)) . \chr(0x80 | ($cp & 0x3F));
    }

    /** YAML 1.2 core schema: null ~ / true false / ints (decimal, 0o octal, 0x hex) / floats / .inf .nan; everything else is a string */
    private static function resolve(string $s): mixed
    {
        if ($s === '' || $s === '~' || $s === 'null' || $s === 'Null' || $s === 'NULL') {
            return null;
        }
        if ($s === 'true' || $s === 'True' || $s === 'TRUE') {
            return true;
        }
        if ($s === 'false' || $s === 'False' || $s === 'FALSE') {
            return false;
        }
        $c = $s[0];
        if (($c < '0' || $c > '9') && $c !== '-' && $c !== '+' && $c !== '.') {
            return $s;
        }
        if (\preg_match('/^[-+]?[0-9]+$/', $s) === 1) {
            $i = (int) $s;
            $d = \ltrim($s, '+');
            return (\strlen(\ltrim($d, '-0')) < 19 || (string) $i === $d) ? $i : (float) $s;
        }
        if (\preg_match('/^0o[0-7]+$/', $s) === 1) {
            return \octdec(\substr($s, 2));
        }
        if (\preg_match('/^0x[0-9a-fA-F]+$/', $s) === 1) {
            return \hexdec(\substr($s, 2));
        }
        if (\preg_match('/^[-+]?(\.[0-9]+|[0-9]+(\.[0-9]*)?)([eE][-+]?[0-9]+)?$/', $s) === 1) {
            return (float) $s;
        }
        if (\preg_match('/^[-+]?\.(inf|Inf|INF)$/', $s) === 1) {
            return $s[0] === '-' ? -\INF : \INF;
        }
        if ($s === '.nan' || $s === '.NaN' || $s === '.NAN') {
            return \NAN;
        }
        return $s;
    }
}
