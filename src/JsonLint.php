<?php

declare(strict_types=1);

namespace Fmt;

/** Finds WHERE a text stops being JSON (json_decode only says that it does), so a config error can name a line. */
final class JsonLint
{
    private string $s = '';
    private int $n = 0;
    private int $i = 0;

    /** byte offset of the first syntax error, -1 when the syntax is fine */
    public static function errorOffset(string $s): int
    {
        $l = new self();
        $l->s = $s;
        $l->n = \strlen($s);
        try {
            $l->value(0);
            $l->ws();
            return $l->i < $l->n ? $l->i : -1;
        } catch (\RuntimeException $e) {
            return $l->i;
        }
    }

    private function ws(): void
    {
        while ($this->i < $this->n && ($this->s[$this->i] === ' ' || $this->s[$this->i] === "\t" || $this->s[$this->i] === "\n" || $this->s[$this->i] === "\r")) {
            $this->i++;
        }
    }

    private function fail(): \RuntimeException
    {
        return new \RuntimeException('json');
    }

    private function value(int $depth): void
    {
        if ($depth > 512) {
            throw $this->fail();
        }
        $this->ws();
        if ($this->i >= $this->n) {
            throw $this->fail();
        }
        $c = $this->s[$this->i];
        if ($c === '{') {
            $this->i++;
            $this->ws();
            if ($this->i < $this->n && $this->s[$this->i] === '}') {
                $this->i++;
                return;
            }
            while (true) {
                $this->ws();
                $this->string();
                $this->ws();
                if ($this->i >= $this->n || $this->s[$this->i] !== ':') {
                    throw $this->fail();
                }
                $this->i++;
                $this->value($depth + 1);
                $this->ws();
                if ($this->i < $this->n && $this->s[$this->i] === ',') {
                    $this->i++;
                    continue;
                }
                if ($this->i < $this->n && $this->s[$this->i] === '}') {
                    $this->i++;
                    return;
                }
                throw $this->fail();
            }
        }
        if ($c === '[') {
            $this->i++;
            $this->ws();
            if ($this->i < $this->n && $this->s[$this->i] === ']') {
                $this->i++;
                return;
            }
            while (true) {
                $this->value($depth + 1);
                $this->ws();
                if ($this->i < $this->n && $this->s[$this->i] === ',') {
                    $this->i++;
                    continue;
                }
                if ($this->i < $this->n && $this->s[$this->i] === ']') {
                    $this->i++;
                    return;
                }
                throw $this->fail();
            }
        }
        if ($c === '"') {
            $this->string();
            return;
        }
        foreach (['true', 'false', 'null'] as $lit) {
            if (\substr($this->s, $this->i, \strlen($lit)) === $lit) {
                $this->i += \strlen($lit);
                return;
            }
        }
        if (\preg_match('/\G-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][-+]?[0-9]+)?/', $this->s, $m, 0, $this->i) === 1 && $m[0] !== '') {
            $this->i += \strlen($m[0]);
            return;
        }
        throw $this->fail();
    }

    private function string(): void
    {
        if ($this->i >= $this->n || $this->s[$this->i] !== '"') {
            throw $this->fail();
        }
        $this->i++;
        while ($this->i < $this->n) {
            $c = $this->s[$this->i];
            if ($c === '"') {
                $this->i++;
                return;
            }
            if (\ord($c) < 0x20) {
                throw $this->fail();
            }
            if ($c === '\\') {
                $this->i++;
                $e = $this->i < $this->n ? $this->s[$this->i] : '';
                if ($e === 'u') {
                    if (!\ctype_xdigit(\substr($this->s, $this->i + 1, 4)) || \strlen(\substr($this->s, $this->i + 1, 4)) !== 4) {
                        throw $this->fail();
                    }
                    $this->i += 4;
                } elseif ($e === '' || \strpos('"\\/bfnrt', $e) === false) {
                    throw $this->fail();
                }
            }
            $this->i++;
        }
        throw $this->fail();
    }
}
