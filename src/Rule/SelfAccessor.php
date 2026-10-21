<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NsAnalysis;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer (priority -11, risky). Inside a class or interface the class's own name (`Foo::BAR`, `new Foo`, `instanceof Foo`, a
 * type in a method signature), also fully qualified, becomes `self`. Whole-file FlatTokens port, namespace by namespace.
 */
final class SelfAccessor extends FlatRule
{
    public function name(): string
    {
        return 'self_accessor';
    }

    public function priority(): int
    {
        return -11;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if (($s->kind[$i] === \__McTokId::T_CLASS || $s->kind[$i] === \__McTokId::T_INTERFACE) && $s->ct[$i] === 0 && !Analyzer::isAnonymousClass($s, $i)) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        foreach (NsAnalysis::scopes($s) as [$ns, $a, $b]) {
            $from = $t->pos[$a] ?? -1;
            $to = $t->pos[$b] ?? -1;
            if ($from < 0 || $to < 0) {
                continue;
            }
            for ($index = $from; $index < $to; $index++) {
                if ($t->i[$index] === -1 || !($t->k[$index] === \__McTokId::T_CLASS || $t->k[$index] === \__McTokId::T_INTERFACE)) {
                    continue;
                }
                if ($t->i[$index] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$index])) {
                    continue;
                }
                $nameIdx = self::nextOf($t, $index, \__McTokId::T_STRING);
                $start = self::nextOf($t, $nameIdx, 123);
                $end = $start < 0 ? -1 : $t->blockEnd($start, 125);
                if ($end < 0) {
                    $s->abort = true; // a class without a body: upstream's findBlockEnd throws, the file stays as it is
                    return;
                }
                $this->replace($s, $t, $ns, $t->content($nameIdx), $start, $end);
                $index = $end;
            }
        }
    }

    private function replace(Source $s, FlatTokens $t, string $ns, string $name, int $start, int $end): void
    {
        $lowerName = \strtolower($name);
        $until = -1;
        for ($i = $start; $i < $end; $i++) {
            if ($i === $until) {
                $until = -1;
            }
            if ($t->i[$i] === -1) {
                continue;
            }
            $k = $t->k[$i];
            if ($k === \__McTokId::T_CLASS && $t->i[$i] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$i])) {
                $i = self::nextOf($t, $i, 123);
                $i = $t->blockEnd($i, 125);
                continue;
            }
            if ($k === \__McTokId::T_FN) {
                $i = self::arrowEnd($t, $i);
                $i = $t->meaningful($i, 1);
                continue;
            }
            if ($k === \__McTokId::T_FUNCTION) {
                if ($t->i[$i] >= 0 && Analyzer::isLambda($s, $t->i[$i])) {
                    $i = self::nextOf($t, $i, 123);
                    $i = $t->blockEnd($i, 125);
                    continue;
                }
                $i = self::nextOf($t, $i, 40);
                $until = -1;
                for ($j = $i + 1; $j < $t->count(); $j++) {
                    if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59)) {
                        $until = $j;
                        break;
                    }
                }
                continue;
            }
            if ($k !== \__McTokId::T_STRING || \strtolower($t->content($i)) !== $lowerName) {
                continue;
            }
            $next = $t->meaningful($i, 1);
            if (self::is($t, $next, \__McTokId::T_NS_SEPARATOR)) {
                continue;
            }
            $classStart = $i;
            $prev = $t->meaningful($i, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                $classStart = $this->classStart($t, $i, $ns);
                if ($classStart < 0) {
                    continue;
                }
                $prev = $t->meaningful($classStart, -1);
            }
            $pk = $prev >= 0 && $t->i[$prev] !== -1 ? $t->k[$prev] : -1;
            if ($pk === \__McTokId::T_STRING || $pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                continue;
            }
            $inSig = $until >= 0 && $i < $until && ($pk === 40 || $pk === 44 || $pk === CT::NULLABLE_TYPE || $pk === CT::TYPE_ALTERNATION || $pk === CT::TYPE_COLON);
            if ($pk === \__McTokId::T_INSTANCEOF || $pk === \__McTokId::T_NEW || self::is($t, $next, \__McTokId::T_DOUBLE_COLON) || $inSig) {
                for ($j = $classStart; $j < $i; $j++) {
                    self::clearMerge($t, $j);
                }
                self::setCode($t, $i, \__McTokId::T_STRING, 'self');
            }
        }
    }

    private function classStart(FlatTokens $t, int $index, string $ns): int
    {
        $ns = ($ns !== '' ? '\\' . $ns : '') . '\\';
        $pieces = Preg::split('/(\\\\)/', $ns, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
        for ($x = \count($pieces) - 1; $x >= 0; $x--) {
            $piece = $pieces[$x];
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                return -1;
            }
            if ($piece === '\\') {
                if (!self::is($t, $index, \__McTokId::T_NS_SEPARATOR)) {
                    return -1;
                }
            } elseif (!(self::is($t, $index, \__McTokId::T_STRING) && \strtolower($t->content($index)) === \strtolower($piece))) {
                return -1;
            }
        }
        return $index;
    }
}
