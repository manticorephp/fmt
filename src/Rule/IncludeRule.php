<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\IncludeFixer (priority 0). `require (  'x' ) ;` -> `require 'x';`: the whitespace before the `;` goes
 * (not before a comment behind it), parentheses around the whole operand go with their inner whitespace (unless the include is an operand
 * of something), and one space follows the keyword. Ported on a whole-file FlatTokens, includes from the last to the first.
 */
final class IncludeRule extends FlatRule
{
    public function name(): string
    {
        return 'include';
    }

    public function priority(): int
    {
        return 0;
    }

    private static function isInclude(Source $s, int $i): bool
    {
        $k = $s->kind[$i];
        return ($k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE) && $s->ct[$i] === 0;
    }

    private static function endOf(Source $s, int $i): int
    {
        for ($j = $i + 1; $j < $s->n; $j++) {
            if (($s->kind[$j] === 59 && $s->ct[$j] === 0) || $s->kind[$j] === \__McTokId::T_CLOSE_TAG) {
                return $j;
            }
        }
        return -1;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if (!self::isInclude($s, $i)) {
                continue;
            }
            $nk = $i + 1 < $s->n ? $s->kind[$i + 1] : 0;
            if ($s->gapBefore[$i + 1] !== ' ' || ($nk === 40 && $s->ct[$i + 1] === 0)) {
                return true;
            }
            $e = self::endOf($s, $i);
            if ($e > 0 && $s->gapBefore[$e] !== '') {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $n = $s->n;
        for ($b = $n - 1; $b >= 0; $b--) {
            if (!self::isInclude($s, $b)) {
                continue;
            }
            $e = self::endOf($s, $b);
            if ($e < 0) {
                continue;
            }
            $begin = $t->pos[$b];
            $end = $t->pos[$e];
            $oSrc = $b + 1 < $n && $s->kind[$b + 1] === 40 && $s->ct[$b + 1] === 0 ? $b + 1 : -1;
            $open = $oSrc >= 0 ? $t->pos[$oSrc] : -1;
            $close = $oSrc >= 0 ? $t->pos[$s->pair[$oSrc]] : -1;
            if (!self::is($t, $end, \__McTokId::T_CLOSE_TAG)) {
                $after = $t->nonWhitespace($end, 1);
                if ($after < 0 || !$t->isComment($after)) {
                    self::removeWs($t, $end, -1);
                }
            }
            if ($open >= 0) {
                $prev = $t->meaningful($begin, -1);
                $next = $t->meaningful($close, 1);
                $semi = $next >= 0 && $t->i[$next] !== -1 && ($t->k[$next] === 59 || $t->k[$next] === \__McTokId::T_CLOSE_TAG);
                if (!$semi && !self::isBlock($s, $t, $prev, $next)) {
                    continue;
                }
                foreach ([$open, $close] as $x) {
                    $nn = $t->nonWhitespace($x, 1);
                    if ($nn < 0 || !$t->isComment($nn)) {
                        self::removeWs($t, $x, -1);
                    }
                    $pp = $t->nonWhitespace($x, -1);
                    if ($pp < 0 || !$t->isComment($pp)) {
                        self::removeWs($t, $x, 1);
                    }
                }
                self::clearMerge($t, $open);
                self::clearMerge($t, $close);
            }
            $nx = $t->nonEmptySibling($begin, 1);
            if ($t->isWs($nx)) {
                $t->c[$nx] = ' ';
            } elseif ($open >= 0 || ($nx >= 0 && ($t->k[$nx] === \__McTokId::T_VARIABLE || $t->k[$nx] === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $t->k[$nx] === FlatTokens::COMMENT))) {
                $t->insertAt($begin + 1, [FlatTokens::ws(' ')]);
            }
        }
    }

    /** BlocksAnalyzer::isBlock: the item at $o opens a block that ends at $c */
    private static function isBlock(Source $s, FlatTokens $t, int $o, int $c): bool
    {
        if ($o < 0 || $c < 0 || $t->i[$o] < 0 || $t->i[$c] < 0) {
            return false;
        }
        $oi = $t->i[$o];
        $p = $s->pair[$oi];
        return $p >= 0 && $p > $oi && $t->i[$c] === $p;
    }
}
