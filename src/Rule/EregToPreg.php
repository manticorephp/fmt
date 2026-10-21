<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\EregToPregFixer (priority 0, risky). `ereg('x', $s)` -> `preg_match('/x/D', $s)` (also eregi, ereg_replace, eregi_replace, split, spliti) for a string literal
 * pattern, a delimiter among `/` `#` `!` and a pattern PCRE accepts. Whole-file FlatTokens port.
 */
final class EregToPreg extends FlatRule
{
    public function name(): string
    {
        return 'ereg_to_preg';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['ereg', 'eregi', 'ereg_replace', 'eregi_replace', 'split', 'spliti']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $map = [['ereg', 'preg_match', ''], ['eregi', 'preg_match', 'i'], ['ereg_replace', 'preg_replace', ''], ['eregi_replace', 'preg_replace', 'i'],
            ['split', 'preg_split', ''], ['spliti', 'preg_split', 'i']];
        $end = $t->count() - 1;
        foreach ($map as $m) {
            $cur = 0;
            while (true) {
                $name = -1;
                $str = -1;
                for ($j = $cur; $j <= $end; $j++) {
                    if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_STRING || \strtolower($t->content($j)) !== $m[0]) {
                        continue;
                    }
                    $o = $t->meaningful($j, 1);
                    if ($o < 0 || $o > $end || !self::is($t, $o, 40)) {
                        continue;
                    }
                    $x = $t->meaningful($o, 1);
                    if ($x < 0 || $x > $end || !self::is($t, $x, \__McTokId::T_CONSTANT_ENCAPSED_STRING)) {
                        continue;
                    }
                    $name = $j;
                    $str = $x;
                    break;
                }
                if ($name < 0) {
                    break;
                }
                $cur = $str;
                if (!$this->isGlobalCall($s, $t, $name)) {
                    continue;
                }
                $next = $t->meaningful($str, 1);
                if ($next < 0 || !(self::is($t, $next, 44) || self::is($t, $next, 41))) {
                    continue;
                }
                $content = $t->content($str);
                if ($content[0] === 'b' || $content[0] === 'B') {
                    $quote = $content[1];
                    $prefix = $content[0];
                    $string = \substr($content, 2, -1);
                } else {
                    $quote = $content[0];
                    $prefix = '';
                    $string = \substr($content, 1, -1);
                }
                $delim = self::bestDelimiter($string);
                $preg = $delim . \addcslashes($string, $delim) . $delim . 'D' . $m[2];
                if (\preg_match($preg . 'u', '') === false && \preg_match($preg, '') === false) {
                    continue;
                }
                self::setCode($t, $name, \__McTokId::T_STRING, $m[1]);
                self::setCode($t, $str, \__McTokId::T_CONSTANT_ENCAPSED_STRING, $prefix . $quote . $preg . $quote);
            }
        }
    }

    private static function bestDelimiter(string $pattern): string
    {
        $best = '';
        $bestCount = -1;
        foreach (['/', '#', '!'] as $d) {
            if (!\str_contains($pattern, $d)) {
                return $d;
            }
            $c = \substr_count($pattern, $d);
            if ($bestCount < 0 || $c < $bestCount) {
                $best = $d;
                $bestCount = $c;
            }
        }
        return $best;
    }
}
