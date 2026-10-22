<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ReturnNotation\SimplifiedNullReturnFixer (priority 16). `return null;` -> `return;` unless the function has a return type
 * (anything but `void` spelled `void`, or a `?`) found by walking back to the nearest `function` whose body ends after the return. Whole-file FlatTokens port;
 * property hooks are scanned for closures in their own range.
 */
final class SimplifiedNullReturn extends FlatRule
{
    public function name(): string
    {
        return 'simplified_null_return';
    }

    public function priority(): int
    {
        return 16;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_RETURN && $s->ct[$i] === 0) {
                $j = $i + 1;
                while ($j < $s->n && $s->kind[$j] === 40) {
                    $j++;
                }
                if ($j < $s->n && $s->kind[$j] === \__McTokId::T_STRING && \strtolower($s->text($j)) === 'null') {
                    return true;
                }
            }
        }
        return false;
    }

    private ?Source $src = null;

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        $this->fixRange($t, 1, $t->count() - 1);
    }

    private function fixRange(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $start; $index < $end; ++$index) {
            if (self::is($t, $index, CT::PROPERTY_HOOK_BRACE_OPEN)) {
                $close = $t->blockEnd($index, CT::PROPERTY_HOOK_BRACE_CLOSE);
                $this->scanRange($t, $index, $close);
                $index = $close;
                continue;
            }
            if (!self::is($t, $index, \__McTokId::T_RETURN)) {
                continue;
            }
            if ($this->needFixing($t, $index)) {
                $this->clear($t, $index);
            }
        }
    }

    private function scanRange(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $start; $index < $end; ++$index) {
            if (self::is($t, $index, \__McTokId::T_FUNCTION)) {
                $open = self::nextOf($t, $index, 123);
                $close = $t->blockEnd($open, 125);
                $this->fixRange($t, $open, $close);
                $index = $close;
            }
        }
    }

    private function clear(FlatTokens $t, int $index): void
    {
        $m = $t->count();
        while (++$index < $m && !(self::is($t, $index, 59) || self::is($t, $index, \__McTokId::T_CLOSE_TAG))) {
            if ($this->shouldClear($t, $index)) {
                $t->clearAt($index);
            }
        }
    }

    private function needFixing(FlatTokens $t, int $index): bool
    {
        if ($this->isStrictOrNullableReturnTypeFunction($t, $index)) {
            return false;
        }
        $content = '';
        while (!(self::is($t, $index, 59) || self::is($t, $index, \__McTokId::T_CLOSE_TAG))) {
            $index = $t->meaningful($index, 1);
            if ($index < 0) {
                return false;
            }
            $content .= $t->content($index);
        }
        $last = $t->content($index);
        $content = \substr($content, 0, -\strlen($last));
        $content = \ltrim($content, '(');
        $content = \rtrim($content, ')');
        return \strtolower($content) === 'null';
    }

    private function isStrictOrNullableReturnTypeFunction(FlatTokens $t, int $returnIndex): bool
    {
        $function = $returnIndex;
        do {
            $function = self::prevOf($t, $function, \__McTokId::T_FUNCTION);
            if ($function < 0) {
                return false;
            }
            $open = self::nextOf($t, $function, 123);
            $close = $open < 0 ? -1 : $t->blockEnd($open, 125);
            if ($open < 0) {
                if ($this->src !== null) {
                    $this->src->abort = true; // findBlockEnd(null): upstream throws a TypeError, the file stays as it is
                }
                return false;
            }
        } while ($close < $returnIndex);
        $possible = $t->meaningful($open, -1);
        $strict = $possible >= 0 && $t->i[$possible] !== -1 && ($t->k[$possible] === \__McTokId::T_STRING || $t->k[$possible] === CT::ARRAY_TYPEHINT)
            && $t->content($possible) !== 'void';
        $nullable = self::nextOf($t, $function, CT::NULLABLE_TYPE);
        return $strict || ($nullable >= 0 && $nullable < $open);
    }

    private function shouldClear(FlatTokens $t, int $index): bool
    {
        if ($t->k[$index] === FlatTokens::COMMENT) {
            return false;
        }
        if ($t->k[$index] !== FlatTokens::WS) {
            return true;
        }
        $m = $t->count();
        $next = $index + 1 < $m ? $index + 1 : -1;
        $prevComment = $index - 1 >= 0 && $t->k[$index - 1] === FlatTokens::COMMENT;
        if ($next >= 0 && ($t->k[$next] === FlatTokens::COMMENT || self::is($t, $next, \__McTokId::T_CLOSE_TAG) || ($prevComment && self::is($t, $next, 59)))) {
            return false;
        }
        return true;
    }
}
