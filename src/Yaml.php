<?php

declare(strict_types=1);

namespace Fmt;

/**
 * The YAML subset a formatter config needs: block mappings and sequences (also a mapping inside a sequence item), flow
 * `[a, b]` / `{k: v}`, quoted and plain scalars, `#` comments. Anchors, tags, block scalars and multi-document files are refused.
 */
final class Yaml
{
    /** @var int[] */
    private array $ind = [];
    /** @var string[] */
    private array $txt = [];
    private int $n = 0;
    private int $pos = 0;

    /** @throws \InvalidArgumentException */
    public static function parse(string $src): mixed
    {
        $y = new self();
        foreach (\preg_split('/\r\n|\n|\r/', $src) as $i => $line) {
            $line = self::stripComment((string) $line);
            if (\trim($line) === '' || \trim($line) === '---') {
                continue;
            }
            if (\preg_match('/^\s*[|>&*!%]/', $line) === 1) {
                throw new \InvalidArgumentException('yaml line ' . ($i + 1) . ': anchors, tags and block scalars are not supported');
            }
            if (\strpos(\substr($line, 0, \strspn($line, " \t")), "\t") !== false) {
                throw new \InvalidArgumentException('yaml line ' . ($i + 1) . ': tab indentation');
            }
            $y->ind[] = \strspn($line, ' ');
            $y->txt[] = \rtrim(\substr($line, \strspn($line, ' ')));
        }
        $y->n = \count($y->txt);
        if ($y->n === 0) {
            return null;
        }
        $v = $y->block($y->ind[0]);
        if ($y->pos < $y->n) {
            throw new \InvalidArgumentException('yaml: unexpected indentation near "' . $y->txt[$y->pos] . '"');
        }
        return $v;
    }

    private static function stripComment(string $l): string
    {
        $q = '';
        $n = \strlen($l);
        for ($i = 0; $i < $n; $i++) {
            $c = $l[$i];
            if ($q !== '') {
                if ($c === '\\' && $q === '"') {
                    $i++;
                } elseif ($c === $q) {
                    $q = '';
                }
            } elseif (($c === '"' || $c === "'") && ($i === 0 || \strpos(" \t[{,:-", $l[$i - 1]) !== false)) {
                $q = $c;
            } elseif ($c === '#' && ($i === 0 || $l[$i - 1] === ' ' || $l[$i - 1] === "\t")) {
                return \substr($l, 0, $i);
            }
        }
        return $l;
    }

    private function block(int $indent): mixed
    {
        $t = $this->txt[$this->pos];
        if ($t === '-' || \str_starts_with($t, '- ')) {
            return $this->seq($indent);
        }
        if (self::keySplit($t) !== null) {
            return $this->map($indent);
        }
        $this->pos++;
        return self::flowOrScalar($t);
    }

    private function seq(int $indent): mixed
    {
        /** @var mixed[] $out */
        $out = [];
        while ($this->pos < $this->n && $this->ind[$this->pos] === $indent && ($this->txt[$this->pos] === '-' || \str_starts_with($this->txt[$this->pos], '- '))) {
            $rest = \ltrim(\substr($this->txt[$this->pos], 1));
            if ($rest === '') {
                $this->pos++;
                if ($this->pos < $this->n && $this->ind[$this->pos] > $indent) {
                    $out[] = $this->block($this->ind[$this->pos]);
                } else {
                    $out[] = null;
                }
            } elseif (self::keySplit($rest) !== null && $rest[0] !== '[' && $rest[0] !== '{' && $rest[0] !== '"' && $rest[0] !== "'") {
                // a mapping starting in the item: re-read the rest of the line as a line of its own, indented past the dash
                $off = $indent + 1 + (\strlen($this->txt[$this->pos]) - 1 - \strlen($rest));
                $this->ind[$this->pos] = $off;
                $this->txt[$this->pos] = $rest;
                $out[] = $this->map($off);
            } else {
                $this->pos++;
                $out[] = self::flowOrScalar($rest);
            }
        }
        return $out;
    }

    private function map(int $indent): mixed
    {
        /** @var array<string, mixed> $out */
        $out = [];
        while ($this->pos < $this->n && $this->ind[$this->pos] === $indent) {
            $kv = self::keySplit($this->txt[$this->pos]);
            if ($kv === null) {
                throw new \InvalidArgumentException('yaml: expected "key: value", got "' . $this->txt[$this->pos] . '"');
            }
            [$k, $rest] = $kv;
            $this->pos++;
            if ($rest === '') {
                if ($this->pos < $this->n && $this->ind[$this->pos] > $indent) {
                    $out[$k] = $this->block($this->ind[$this->pos]);
                } elseif ($this->pos < $this->n && $this->ind[$this->pos] === $indent && ($this->txt[$this->pos] === '-' || \str_starts_with($this->txt[$this->pos], '- '))) {
                    $out[$k] = $this->seq($indent); // a sequence may sit at the key's own indent
                } else {
                    $out[$k] = null;
                }
            } else {
                $out[$k] = self::flowOrScalar($rest);
            }
        }
        if ($this->pos < $this->n && $this->ind[$this->pos] > $indent) {
            throw new \InvalidArgumentException('yaml: unexpected indentation near "' . $this->txt[$this->pos] . '"');
        }
        return $out;
    }

    /** @return array{0: string, 1: string}|null key and the rest of the line, null when the line is no `key:` line */
    private static function keySplit(string $t): ?array
    {
        $c = $t[0];
        if ($c === '"' || $c === "'") {
            $end = self::quotedEnd($t, 0);
            if ($end < 0 || ($end + 1 < \strlen($t) && $t[$end + 1] !== ':')) {
                return null;
            }
            if ($end + 1 >= \strlen($t)) {
                return null;
            }
            $after = \substr($t, $end + 2);
            if ($after !== '' && $after[0] !== ' ') {
                return null;
            }
            return [(string) self::scalar(\substr($t, 0, $end + 1)), \ltrim($after)];
        }
        if ($c === '[' || $c === '{') {
            return null;
        }
        $n = \strlen($t);
        for ($i = 0; $i < $n; $i++) {
            if ($t[$i] === ':' && ($i + 1 === $n || $t[$i + 1] === ' ')) {
                return [\trim(\substr($t, 0, $i)), \ltrim(\substr($t, $i + 1))];
            }
        }
        return null;
    }

    private static function quotedEnd(string $t, int $from): int
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

    private static function flowOrScalar(string $s): mixed
    {
        if ($s[0] === '[' || $s[0] === '{') {
            $i = 0;
            $v = self::flow($s, $i);
            if (\trim(\substr($s, $i)) !== '') {
                throw new \InvalidArgumentException('yaml: trailing text after flow value "' . $s . '"');
            }
            return $v;
        }
        return self::scalar($s);
    }

    private static function flow(string $s, int &$i): mixed
    {
        $n = \strlen($s);
        while ($i < $n && $s[$i] === ' ') {
            $i++;
        }
        if ($i >= $n) {
            throw new \InvalidArgumentException('yaml: unterminated flow value');
        }
        if ($s[$i] === '[') {
            $i++;
            /** @var mixed[] $out */
            $out = [];
            while (true) {
                while ($i < $n && $s[$i] === ' ') {
                    $i++;
                }
                if ($i >= $n) {
                    throw new \InvalidArgumentException('yaml: unterminated [ in "' . $s . '"');
                }
                if ($s[$i] === ']') {
                    $i++;
                    return $out;
                }
                $out[] = self::flow($s, $i);
                while ($i < $n && $s[$i] === ' ') {
                    $i++;
                }
                if ($i < $n && $s[$i] === ',') {
                    $i++;
                }
            }
        }
        if ($s[$i] === '{') {
            $i++;
            /** @var array<string, mixed> $out */
            $out = [];
            while (true) {
                while ($i < $n && $s[$i] === ' ') {
                    $i++;
                }
                if ($i >= $n) {
                    throw new \InvalidArgumentException('yaml: unterminated { in "' . $s . '"');
                }
                if ($s[$i] === '}') {
                    $i++;
                    return $out;
                }
                $k = self::flowItem($s, $i, ':');
                $i++; // the colon
                $out[(string) $k] = self::flow($s, $i);
                while ($i < $n && $s[$i] === ' ') {
                    $i++;
                }
                if ($i < $n && $s[$i] === ',') {
                    $i++;
                }
            }
        }
        return self::flowItem($s, $i, ',]}');
    }

    /** a scalar inside a flow collection, up to one of $stops (quoted scalars are read whole) */
    private static function flowItem(string $s, int &$i, string $stops): mixed
    {
        $n = \strlen($s);
        while ($i < $n && $s[$i] === ' ') {
            $i++;
        }
        if ($i < $n && ($s[$i] === '"' || $s[$i] === "'")) {
            $e = self::quotedEnd($s, $i);
            if ($e < 0) {
                throw new \InvalidArgumentException('yaml: unterminated quote in "' . $s . '"');
            }
            $v = self::scalar(\substr($s, $i, $e - $i + 1));
            $i = $e + 1;
            while ($i < $n && $s[$i] === ' ') {
                $i++;
            }
            return $v;
        }
        $st = $i;
        while ($i < $n && \strpos($stops, $s[$i]) === false) {
            $i++;
        }
        return self::scalar(\trim(\substr($s, $st, $i - $st)));
    }

    private static function scalar(string $s): mixed
    {
        if ($s === '' || $s === '~' || $s === 'null' || $s === 'Null' || $s === 'NULL') {
            return null;
        }
        $c = $s[0];
        if ($c === '"') {
            $e = self::quotedEnd($s, 0);
            if ($e !== \strlen($s) - 1) {
                throw new \InvalidArgumentException('yaml: bad quoted scalar ' . $s);
            }
            return self::unescape(\substr($s, 1, -1));
        }
        if ($c === "'") {
            $e = self::quotedEnd($s, 0);
            if ($e !== \strlen($s) - 1) {
                throw new \InvalidArgumentException('yaml: bad quoted scalar ' . $s);
            }
            return \str_replace("''", "'", \substr($s, 1, -1));
        }
        if ($s === 'true' || $s === 'True' || $s === 'TRUE') {
            return true;
        }
        if ($s === 'false' || $s === 'False' || $s === 'FALSE') {
            return false;
        }
        if (\preg_match('/^[-+]?(0|[1-9][0-9]*)$/', $s) === 1) {
            return (int) $s;
        }
        if (\preg_match('/^[-+]?([0-9]+\.[0-9]*|\.[0-9]+)([eE][-+]?[0-9]+)?$/', $s) === 1) {
            return (float) $s;
        }
        return $s;
    }

    private static function unescape(string $s): string
    {
        if (\strpos($s, '\\') === false) {
            return $s;
        }
        $out = '';
        $n = \strlen($s);
        for ($i = 0; $i < $n; $i++) {
            if ($s[$i] !== '\\' || $i + 1 >= $n) {
                $out .= $s[$i];
                continue;
            }
            $i++;
            $c = $s[$i];
            if ($c === 'n') {
                $out .= "\n";
            } elseif ($c === 't') {
                $out .= "\t";
            } elseif ($c === 'r') {
                $out .= "\r";
            } elseif ($c === '0') {
                $out .= "\0";
            } elseif ($c === 'x' && $i + 2 < $n + 0 && \ctype_xdigit(\substr($s, $i + 1, 2))) {
                $out .= \chr((int) \hexdec(\substr($s, $i + 1, 2)));
                $i += 2;
            } else {
                $out .= $c; // \\ \" \/ and the rest stand for themselves
            }
        }
        return $out;
    }
}
