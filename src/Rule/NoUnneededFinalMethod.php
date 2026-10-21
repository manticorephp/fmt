<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer (priority 0, risky). `final` is removed from the methods of a `final` class and of an enum, and (private_methods)
 * from private non-constructor methods of any class. Whole-file FlatTokens port over the Source's classy elements (nothing is inserted, so the indexes are stable).
 */
final class NoUnneededFinalMethod extends FlatRule
{
    public function __construct(private bool $privateMethods)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'no_unneeded_final_method';
    }

    public function priority(): int
    {
        return 0;
    }

    /** a `final` followed only by method modifiers and then `function` */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        $hasClassy = false;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_CLASS || $k === \__McTokId::T_ENUM) && $s->ct[$i] === 0) {
                $hasClassy = true;
            }
        }
        if (!$hasClassy) {
            return false;
        }
        for ($i = 0; $i + 1 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_FINAL) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $k = $s->kind[$j];
                if ($k === \__McTokId::T_FUNCTION) {
                    return true;
                }
                if (!($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_ABSTRACT
                    || $k === \__McTokId::T_FINAL)) {
                    break;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, bool> $enums */
        $enums = [];
        /** @var array<int, bool> $finals */
        $finals = [];
        foreach (Analyzer::classyElements($s) as $index => $el) {
            if ($el[0] !== Analyzer::E_METHOD) {
                continue;
            }
            $classIdx = $el[1];
            $q = $t->pos[$index] ?? -1;
            $cq = $t->pos[$classIdx] ?? -1;
            if ($q < 0 || $cq < 0) {
                continue;
            }
            if (!isset($enums[$classIdx])) {
                $enums[$classIdx] = $t->k[$cq] === \__McTokId::T_ENUM;
            }
            $finalIdx = -1;
            $private = false;
            $prev = $q;
            do {
                $prev = $t->meaningful($prev, -1);
                $k = $prev >= 0 ? $t->k[$prev] : -1;
                if ($k === \__McTokId::T_PRIVATE) {
                    $private = true;
                } elseif ($k === \__McTokId::T_FINAL) {
                    $finalIdx = $prev;
                }
            } while ($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL
                || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_STATIC);
            if ($enums[$classIdx]) {
                $this->clearFinal($t, $finalIdx);
                continue;
            }
            if (!isset($finals[$classIdx])) {
                $final = false;
                $x = $cq;
                while (true) {
                    $x = $t->meaningful($x, -1);
                    if ($x < 0) {
                        break;
                    }
                    if (self::is($t, $x, \__McTokId::T_FINAL)) {
                        $final = true;
                    } elseif (self::is($t, $x, \__McTokId::T_ABSTRACT) || self::is($t, $x, \__McTokId::T_READONLY)) {
                        continue;
                    } else {
                        break;
                    }
                }
                $finals[$classIdx] = $final;
            }
            if ($finals[$classIdx]) {
                $this->clearFinal($t, $finalIdx);
                continue;
            }
            $nameIdx = $t->meaningful($q, 1);
            $ctor = $nameIdx >= 0 && \strtolower($t->content($nameIdx)) === '__construct';
            if (!$private || !$this->privateMethods || $ctor) {
                continue;
            }
            $this->clearFinal($t, $finalIdx);
        }
    }

    private function clearFinal(FlatTokens $t, int $index): void
    {
        if ($index < 0) {
            return;
        }
        $t->clearAt($index);
        $index++;
        if (self::wsOnly($t, $index, " \t\n\r\0\x0B")) {
            $t->clearAt($index);
        }
    }
}
