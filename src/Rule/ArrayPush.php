<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\ArrayPushFixer (priority 0, risky). The statement `array_push($x, $y);` -> `$x[] = $y;` (one pushed value, no spread). Whole-file FlatTokens
 * port, last call first.
 */
final class ArrayPush extends FlatRule
{
    public function name(): string
    {
        return 'array_push';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        return $s->n > 7 && self::hasCallOf($s, ['array_push']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 7; $index > 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING || \strtolower($t->content($index)) !== 'array_push') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $index)) {
                continue;
            }
            $call = $index;
            $index = $t->meaningful($index, -1);
            $ns = -1;
            if (self::is($t, $index, \__McTokId::T_NS_SEPARATOR)) {
                $ns = $index;
                $index = $t->meaningful($index, -1);
            }
            if (!(self::is($t, $index, 59) || self::is($t, $index, 123) || self::is($t, $index, 125) || self::is($t, $index, 41) || self::is($t, $index, \__McTokId::T_OPEN_TAG))) {
                continue;
            }
            $open = $t->meaningful($call, 1);
            $close = $t->blockEnd($open, 41);
            $after = $t->meaningful($close, 1);
            if ($after >= 0 && !(self::is($t, $after, 59) || self::is($t, $after, \__McTokId::T_CLOSE_TAG))) {
                continue;
            }
            $stop = $this->firstArgumentEnd($t, $open);
            $stop = $t->meaningful($stop, 1);
            if (!self::is($t, $stop, 44)) {
                continue;
            }
            $secondStart = $t->meaningful($stop, 1);
            if (!$this->secondArgumentOk($t, $secondStart, $close)) {
                continue;
            }
            self::clearMerge($t, $close);
            self::clearMerge($t, $stop);
            $t->insertAt($stop, [FlatTokens::code(91, '['), FlatTokens::code(93, ']'), FlatTokens::ws(' '), FlatTokens::code(61, '=')]);
            self::clearMerge($t, $open);
            self::clearMerge($t, $call);
            if ($ns >= 0) {
                self::clearMerge($t, $ns);
            }
        }
    }

    private function firstArgumentEnd(FlatTokens $t, int $index): int
    {
        $next = $t->meaningful($index, 1);
        while ($next >= 0 && $t->i[$next] !== -1) {
            $k = $t->k[$next];
            if (!($k === 36 || $k === 91 || $k === 40 || $k === CT::ARRAY_INDEX_BRACE_OPEN || $k === CT::DYNAMIC_PROP_BRACE_OPEN || $k === CT::DYNAMIC_VAR_BRACE_OPEN
                || $k === CT::NAMESPACE_OPERATOR || $k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE)) {
                break;
            }
            $det = self::blockEdge($k);
            if ($det !== null) {
                $next = $t->blockEnd($next, TypeWalk::closer($det[0]));
            }
            $index = $next;
            $next = $t->meaningful($next, 1);
        }
        if (self::is($t, $next, \__McTokId::T_OBJECT_OPERATOR)) {
            return $this->firstArgumentEnd($t, $next);
        }
        if (self::is($t, $next, \__McTokId::T_DOUBLE_COLON)) {
            return $this->firstArgumentEnd($t, $t->meaningful($next, 1));
        }
        return $index;
    }

    private function secondArgumentOk(FlatTokens $t, int $index, int $end): bool
    {
        if (self::is($t, $index, \__McTokId::T_ELLIPSIS)) {
            return false;
        }
        for (; $index <= $end; $index++) {
            $det = $t->i[$index] === -1 ? null : self::blockEdge($t->k[$index]);
            while ($det !== null && $det[1] === 1) {
                $index = $t->blockEnd($index, TypeWalk::closer($det[0]));
                $index = $t->meaningful($index, 1);
                $det = $index < 0 || $t->i[$index] === -1 ? null : self::blockEdge($t->k[$index]);
            }
            if ($index < 0) {
                return true;
            }
            $k = $t->k[$index];
            if ($t->i[$index] !== -1 && ($k === 44 || $k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM || $k === \__McTokId::T_LOGICAL_AND
                || $k === \__McTokId::T_LOGICAL_OR || $k === \__McTokId::T_LOGICAL_XOR)) {
                return false;
            }
        }
        return true;
    }
}
