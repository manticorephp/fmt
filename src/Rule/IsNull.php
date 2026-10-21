<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\IsNullFixer (priority 1, risky). `is_null($x)` -> `null === $x`, `!is_null($x)` -> `null !== $x` (parentheses kept around
 * an argument holding `?` `?:` `=` `??`, added next to a cast / comparison). Whole-file FlatTokens port.
 */
final class IsNull extends FlatRule
{
    public function name(): string
    {
        return 'is_null';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['is_null']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $cur = 0;
        while (true) {
            $b = $this->findCall($s, $t, 'is_null', $cur, $t->count() - 1);
            if ($b === null) {
                break;
            }
            [$isNull, $open] = $b;
            $cur = $open;
            $next = $t->meaningful($open, 1);
            if (self::is($t, $next, 41) || self::is($t, $next, \__McTokId::T_ELLIPSIS)) {
                continue;
            }
            $referenceEnd = $t->blockEnd($open, 41);
            $colon = $t->meaningful($next, 1);
            if ($colon >= 0 && $t->k[$colon] === CT::NAMED_ARGUMENT_COLON && $t->i[$colon] !== -1) {
                if (!($t->k[$next] === CT::NAMED_ARGUMENT_NAME && $t->content($next) === 'value')) {
                    continue;
                }
                self::clearMerge($t, $colon);
                self::clearMerge($t, $next);
                self::removeWs($t, $open, 1);
            }
            $prev = $t->meaningful($isNull, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                self::removeWs($t, $prev, 1);
                $t->clearAt($prev);
                $prev = $t->meaningful($prev, -1);
            }
            $inverted = false;
            if (self::is($t, $prev, 33)) {
                $inverted = true;
                self::removeWs($t, $prev, 1);
                $t->clearAt($prev);
            }
            $dangerous = false;
            for ($p = $open; $p <= $referenceEnd; $p++) {
                $c = $t->content($p);
                if ($c === '?' || $c === '?:' || $c === '=' || $c === '??') {
                    $dangerous = true;
                    break;
                }
            }
            $left = $t->meaningful($isNull, -1);
            $right = $t->meaningful($referenceEnd, 1);
            $lk = $left >= 0 ? $t->k[$left] : -1;
            $rk = $right >= 0 ? $t->k[$right] : -1;
            $wrap = ($left >= 0 && $t->i[$left] !== -1 && self::isCastKind($lk)) || self::isCmp($t, $left) || self::isCmp($t, $right);
            $pi = $t->meaningful($referenceEnd, -1);
            if (self::is($t, $pi, 44)) {
                self::clearMerge($t, $pi);
            }
            if (!$dangerous) {
                self::removeWs($t, $referenceEnd, -1);
                $t->clearAt($referenceEnd);
                self::removeWs($t, $open, -1);
                self::removeWs($t, $open, 1);
                $t->clearAt($open);
            }
            $repl = [FlatTokens::code(\__McTokId::T_STRING, 'null'), FlatTokens::ws(' '),
                FlatTokens::code($inverted ? \__McTokId::T_IS_NOT_IDENTICAL : \__McTokId::T_IS_IDENTICAL, $inverted ? '!==' : '==='), FlatTokens::ws(' ')];
            if ($wrap) {
                \array_unshift($repl, FlatTokens::code(40, '('));
                $t->insertAt($referenceEnd + 1, [FlatTokens::code(41, ')')]);
            }
            self::overrideRange($t, $isNull, $isNull, $repl);
            $cur = $isNull;
        }
    }

    private static function isCmp(FlatTokens $t, int $q): bool
    {
        if ($q < 0 || $t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_IS_EQUAL || $k === \__McTokId::T_IS_NOT_EQUAL || $k === \__McTokId::T_IS_IDENTICAL || $k === \__McTokId::T_IS_NOT_IDENTICAL;
    }
}
