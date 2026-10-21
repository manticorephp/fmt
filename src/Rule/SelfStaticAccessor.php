<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\SelfStaticAccessorFixer (priority -10). Inside an enum, a `final` class or an anonymous class `static::` / `new static` /
 * `instanceof static` become `self` (lambda bodies are skipped unless they hold a class of their own). The token becomes a T_STRING `self`.
 */
final class SelfStaticAccessor extends GapRule
{
    private Source $s;

    public function name(): string
    {
        return 'self_static_accessor';
    }

    public function priority(): int
    {
        return -10;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $st = false;
        $cl = false;
        $op = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $st = $st || $k === \__McTokId::T_STATIC;
            $cl = $cl || (($k === \__McTokId::T_CLASS && $s->ct[$i] === 0) || $k === \__McTokId::T_ENUM);
            $op = $op || $k === \__McTokId::T_DOUBLE_COLON || $k === \__McTokId::T_NEW || $k === \__McTokId::T_INSTANCEOF;
        }
        return $st && $cl && $op;
    }

    private function nextClassy(int $from): int
    {
        $s = $this->s;
        for ($j = $from + 1; $j < $s->n; $j++) {
            if ($s->ct[$j] === 0 && ($s->kind[$j] === \__McTokId::T_CLASS || $s->kind[$j] === \__McTokId::T_ENUM)) {
                return $j;
            }
        }
        return -1;
    }

    /** the next plain token of kind $k after $from, $s->n when none */
    private function nextPlain(int $from, int $k): int
    {
        $s = $this->s;
        for ($j = $from + 1; $j < $s->n; $j++) {
            if ($s->ct[$j] === 0 && $s->kind[$j] === $k) {
                return $j;
            }
        }
        return $s->n;
    }

    private function isFinal(int $i): bool
    {
        $s = $this->s;
        $j = $i;
        while (true) {
            $j--;
            if ($j < 0) {
                return false;
            }
            $k = $s->kind[$j];
            if ($k === \__McTokId::T_FINAL) {
                return true;
            }
            if ($k !== \__McTokId::T_ABSTRACT && $k !== \__McTokId::T_READONLY) {
                return false;
            }
        }
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $classy = $this->nextClassy(0);
        while ($classy >= 0) {
            if ($s->kind[$classy] === \__McTokId::T_CLASS) {
                if ($this->isFinal($classy) || Analyzer::isAnonymousClass($s, $classy)) {
                    $classy = $this->fixClassy($classy);
                }
            } else {
                $classy = $this->fixClassy($classy);
            }
            $classy = $this->nextClassy($classy);
        }
    }

    private function toSelf(int $i): void
    {
        $this->s->setKind($i, \__McTokId::T_STRING);
        $this->s->setText($i, 'self');
    }

    private function fixClassy(int $index): int
    {
        $s = $this->s;
        $n = $s->n;
        $index = $this->nextPlain($index, 123);
        $open = 1;
        while ($open > 0) {
            ++$index;
            if ($index >= $n) {
                return $n - 1;
            }
            $k = $s->kind[$index];
            $plain = $s->ct[$index] === 0;
            if ($plain && $k === 123) {
                ++$open;
                continue;
            }
            if ($plain && $k === 125) {
                --$open;
                continue;
            }
            if ($plain && $k === \__McTokId::T_FUNCTION) {
                if (Analyzer::isLambda($s, $index)) {
                    $index = $this->nextPlain($index, 123);
                    $openCount = 1;
                    do {
                        $b = $this->nextPlain($index, 125);
                        $o = $this->nextPlain($index, 123);
                        $c = $this->nextPlain($index, \__McTokId::T_CLASS);
                        $index = \min($b, $o, $c);
                        if ($index >= $n) {
                            return $n - 1;
                        }
                        if ($s->kind[$index] === 125) {
                            --$openCount;
                        } elseif ($s->kind[$index] === 123) {
                            ++$openCount;
                        } else {
                            $index = $this->fixClassy($index);
                        }
                    } while ($openCount > 0);
                }
                continue;
            }
            if ($plain && ($k === \__McTokId::T_NEW || $k === \__McTokId::T_INSTANCEOF)) {
                $index++;
                if ($index < $n && $s->kind[$index] === \__McTokId::T_STATIC && $s->ct[$index] === 0) {
                    $this->toSelf($index);
                }
                continue;
            }
            if (!$plain || $k !== \__McTokId::T_STATIC) {
                continue;
            }
            $next = $index + 1;
            if ($next >= $n || $s->kind[$next] !== \__McTokId::T_DOUBLE_COLON) {
                continue;
            }
            $this->toSelf($index);
            $index = $next;
        }
        return $index;
    }
}
