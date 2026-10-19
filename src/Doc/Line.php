<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/**
 * Port of PhpCsFixer\DocBlock\Line: one line of a docblock, its line break included. The two predicates every phpdoc fixer asks
 * (useful content, a tag) are cached until the content changes, and answered without PCRE for an ASCII line (where upstream's /u
 * patterns and byte patterns agree and `\s` is [ \t\n\x0b\x0c\r]).
 */
final class Line
{
    /** bit 0: useful computed, bit 1: useful, bit 2: tag computed, bit 3: tag */
    private int $memo = 0;

    public function __construct(private string $content, private bool $ascii = false)
    {
    }

    public function get(): string
    {
        return $this->content;
    }

    public function set(string $content): void
    {
        if ($content !== $this->content) {
            $this->content = $content;
            $this->memo = 0;
            $this->ascii = false;
        }
    }

    public function containsUsefulContent(): bool
    {
        if (($this->memo & 1) === 0) {
            $c = $this->content;
            if ($this->ascii) {
                $r = self::starThenNonSpace($c, false) && \trim(\str_replace(['/', '*'], ' ', $c)) !== '';
            } else {
                $r = Preg::test('/\*\s*\S+/', $c) && \trim(\str_replace(['/', '*'], ' ', $c)) !== '';
            }
            $this->memo |= $r ? 3 : 1;
        }
        return ($this->memo & 2) !== 0;
    }

    public function containsATag(): bool
    {
        if (($this->memo & 4) === 0) {
            $c = $this->content;
            if (!\str_contains($c, '@')) {
                $r = false;
            } elseif ($this->ascii) {
                $r = self::starThenNonSpace($c, true);
            } else {
                $r = Preg::test('/\*\s*@/', $c);
            }
            $this->memo |= $r ? 12 : 4;
        }
        return ($this->memo & 8) !== 0;
    }

    /** ASCII `/\*\s*\S/` (or `/\*\s*@/` when $at) */
    private static function starThenNonSpace(string $c, bool $at): bool
    {
        $n = \strlen($c);
        $p = \strpos($c, '*');
        while ($p !== false) {
            $q = $p + 1 + \strspn($c, " \t\n\x0b\x0c\r", $p + 1);
            if ($q < $n && ($at ? $c[$q] === '@' : true)) {
                return true;
            }
            $p = \strpos($c, '*', $p + 1);
        }
        return false;
    }

    public function isTheStart(): bool
    {
        return \str_contains($this->content, '/**');
    }

    public function isTheEnd(): bool
    {
        return \str_contains($this->content, '*/');
    }

    public function remove(): void
    {
        $this->set('');
    }

    public function addBlank(): void
    {
        if (!Preg::match('/^(\h*\*)[^\r\n]*(\r?\n)$/', $this->content, $m)) {
            return;
        }
        $this->set($this->content . $m[1] . $m[2]);
    }
}
