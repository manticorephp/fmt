<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\StaticPrivateMethodFixer (priority 1, risky). A private method that uses no `$this` (but calls of itself), no closure / nested function and no
 * `debug_backtrace` becomes `static`, and `$this->name(` calls of the fixed methods inside the class become `self::name(`; repeated until nothing changes. Whole-file FlatTokens port.
 */
final class StaticPrivateMethod extends FlatRule
{
    public function name(): string
    {
        return 'static_private_method';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        $c = false;
        $p = false;
        $f = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_CLASS) {
                $c = true;
            } elseif ($k === \__McTokId::T_PRIVATE) {
                $p = true;
            } elseif ($k === \__McTokId::T_FUNCTION) {
                $f = true;
            }
        }
        return $c && $p && $f;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        do {
            $changed = false;
            $end = $t->count() - 3;
            for ($index = $end; $index > 0; $index--) {
                if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_CLASS) {
                    continue;
                }
                $open = self::nextOf($t, $index, 123);
                $close = $open < 0 ? -1 : $t->blockEnd($open, 125);
                if ($close < 0) {
                    $s->abort = true;
                    return;
                }
                $changed = $this->fixClass($s, $t, $open) || $changed;
            }
        } while ($changed);
    }

    /** @return int[]|null the next method (function keyword, body open, body close) before $index in the class body, scanning down from $from */
    private function nextMethod(FlatTokens $t, int $open, int $from): ?array
    {
        for ($index = $from; $index > $open + 1; $index--) {
            if (self::is($t, $index, 125)) {
                $index = $t->blockStart($index, 123);
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $abstract = false;
            for ($i = $index; $i >= 0; $i--) {
                $i = $t->meaningful($i, -1);
                if ($i < 0) {
                    break;
                }
                $k = $t->i[$i] === -1 ? -1 : $t->k[$i];
                if ($k === \__McTokId::T_ABSTRACT) {
                    $abstract = true;
                    continue;
                }
                if ($k === \__McTokId::T_STATIC || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC) {
                    continue;
                }
                break;
            }
            if ($abstract) {
                continue;
            }
            $mo = self::nextOf($t, $index, 123);
            $mc = $mo < 0 ? -1 : $t->blockEnd($mo, 125);
            return [$index, $mo, $mc];
        }
        return null;
    }

    private function fixClass(Source $s, FlatTokens $t, int $open): bool
    {
        /** @var array<string, bool> $fixed */
        $fixed = [];
        $close = $t->blockEnd($open, 125);
        $from = $close - 1;
        while (($m = $this->nextMethod($t, $open, $from)) !== null) {
            [$fn, $mo, $mc] = $m;
            $from = $fn - 1;
            if ($mo < 0 || $mc < 0 || $this->skip($s, $t, $fn, $mo, $mc)) {
                continue;
            }
            $name = $t->content($t->meaningful($fn, 1));
            $fixed[$name] = true;
            $t->insertAt($fn, [FlatTokens::code(\__McTokId::T_STATIC, 'static'), FlatTokens::ws(' ')]);
        }
        if (\count($fixed) === 0) {
            return false;
        }
        $close = $t->blockEnd($open, 125);
        $from = $close - 1;
        while (($m = $this->nextMethod($t, $open, $from)) !== null) {
            [$fn, $mo, $mc] = $m;
            $from = $fn - 1;
            $this->fixRefs($s, $t, $mo, $mc, $fixed);
        }
        return true;
    }

    private function skip(Source $s, FlatTokens $t, int $fn, int $mo, int $mc): bool
    {
        $ni = $t->meaningful($fn, 1);
        $name = \strtolower($t->content($ni));
        if ($name === '__clone' || $name === '__construct' || $name === '__destruct' || $name === '__wakeup') {
            return true;
        }
        $prev = $t->meaningful($fn, -1);
        if (self::is($t, $prev, \__McTokId::T_FINAL)) {
            $prev = $t->meaningful($prev, -1);
        }
        if (!self::is($t, $prev, \__McTokId::T_PRIVATE)) {
            return true;
        }
        $pp = $t->meaningful($prev, -1);
        if (self::is($t, $pp, \__McTokId::T_STATIC)) {
            return true;
        }
        for ($index = $mo + 1; $index < $mc - 1; $index++) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_CLASS && $t->i[$index] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$index])) {
                $ao = self::nextOf($t, $index, 123);
                $index = $t->blockEnd($ao, 125);
                continue;
            }
            if ($k === \__McTokId::T_FUNCTION) {
                return true;
            }
            if ($k === \__McTokId::T_VARIABLE && $t->content($index) === '$this') {
                $op = $t->meaningful($index, 1);
                $mn = $t->meaningful($op, 1);
                $ab = $t->meaningful($mn, 1);
                if (!self::is($t, $op, \__McTokId::T_OBJECT_OPERATOR) || $mn < 0 || $name !== $t->content($mn) || !self::is($t, $ab, 40)) {
                    return true;
                }
            }
            if ($k === \__McTokId::T_STRING && $t->content($index) === 'debug_backtrace') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, bool> $fixed */
    private function fixRefs(Source $s, FlatTokens $t, int $mo, int $mc, array $fixed): void
    {
        for ($index = $mo + 1; $index < $mc - 1; $index++) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_FUNCTION) {
                $prev = $t->meaningful($index, -1);
                $cs = self::nextOf($t, $index, 123);
                $ce = $t->blockEnd($cs, 125);
                if (!self::is($t, $prev, \__McTokId::T_STATIC)) {
                    $this->fixRefs($s, $t, $cs, $ce, $fixed);
                }
                $index = $ce;
                continue;
            }
            if ($k === \__McTokId::T_CLASS && $t->i[$index] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$index])) {
                $ao = self::nextOf($t, $index, 123);
                $index = $t->blockEnd($ao, 125);
                continue;
            }
            if ($k !== \__McTokId::T_VARIABLE || $t->content($index) !== '$this') {
                continue;
            }
            $op = $t->meaningful($index, 1);
            if (!self::is($t, $op, \__McTokId::T_OBJECT_OPERATOR)) {
                continue;
            }
            $mn = $t->meaningful($op, 1);
            $ab = $t->meaningful($mn, 1);
            if (!self::is($t, $ab, 40)) {
                continue;
            }
            if (!isset($fixed[$t->content($mn)])) {
                continue;
            }
            self::setCode($t, $index, \__McTokId::T_STRING, 'self');
            self::setCode($t, $op, \__McTokId::T_DOUBLE_COLON, '::');
        }
    }
}
