<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer (priority -18). In a function body, a `return;` that is the last statement (the `;` is
 * followed by the body's `}`) and does not follow `else` / `)` is deleted together with its `;`; repeated returns peel off from the end. Deleting
 * a token with its surrounding whitespace merged is, as text, deleting the token unit.
 */
final class NoUselessReturn extends EditRule
{
    public function name(): string
    {
        return 'no_useless_return';
    }

    public function priority(): int
    {
        return -18;
    }

    public function prepare(Source $s): bool
    {
        $f = false;
        $r = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $f = $f || ($k === \__McTokId::T_FUNCTION && $s->ct[$i] === 0);
            $r = $r || $k === \__McTokId::T_RETURN;
        }
        return $f && $r;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $del = [];
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_FUNCTION || $s->ct[$i] !== 0) {
                continue;
            }
            $o = $i + 1;
            while ($o < $n && !($s->ct[$o] === 0 && ($s->kind[$o] === 59 || $s->kind[$o] === 123))) {
                $o++;
            }
            if ($o >= $n || $s->kind[$o] !== 123) {
                continue;
            }
            $end = $s->pair[$o];
            if ($end < 0) {
                continue;
            }
            for ($j = $end; $j > $o; $j--) {
                if ($s->kind[$j] !== \__McTokId::T_RETURN || $s->ct[$j] !== 0 || isset($del[$j])) {
                    continue;
                }
                $a = $j + 1;
                while ($a < $n && isset($del[$a])) {
                    $a++;
                }
                if ($a >= $n || $s->kind[$a] !== 59 || $s->ct[$a] !== 0) {
                    continue;
                }
                $b = $a + 1;
                while ($b < $n && isset($del[$b])) {
                    $b++;
                }
                if ($b !== $end) {
                    continue;
                }
                $p = $j - 1;
                while ($p >= 0 && isset($del[$p])) {
                    $p--;
                }
                if ($p >= 0 && (($s->kind[$p] === \__McTokId::T_ELSE && $s->ct[$p] === 0) || ($s->kind[$p] === 41 && $s->ct[$p] === 0))) {
                    continue;
                }
                $del[$j] = true;
                $del[$a] = true;
            }
        }
        foreach ($del as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, '');
        }
    }
}
