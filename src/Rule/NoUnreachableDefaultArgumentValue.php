<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\NoUnreachableDefaultArgumentValueFixer (priority 0, risky). In a function / closure / arrow function signature the default value of an
 * argument that is followed by a non-default (non-variadic) one is removed, unless it is `= null` on a non-nullable type. Whole-file FlatTokens port.
 */
final class NoUnreachableDefaultArgumentValue extends FlatRule
{
    public function name(): string
    {
        return 'no_unreachable_default_argument_value';
    }

    public function priority(): int
    {
        return 0;
    }

    /** a `function` / `fn` whose parameter list has a default value followed (somewhere) by a parameter without one: a superset of the rewrites */
    protected function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if (($k !== \__McTokId::T_FUNCTION && $k !== \__McTokId::T_FN) || $s->ct[$i] !== 0) {
                continue;
            }
            $o = $i + 1;
            while ($o < $n && $s->kind[$o] !== 40) {
                $o++;
            }
            if ($o >= $n) {
                break;
            }
            $c = $s->pair[$o];
            $sawDefault = false;
            for ($j = $o + 1; $j < $c; $j++) {
                $jk = $s->ct[$j] !== 0 ? $s->ct[$j] : $s->kind[$j];
                if (TypeWalk::closer($jk) >= 0 && $s->pair[$j] > $j) {
                    $j = $s->pair[$j];
                    continue;
                }
                if ($jk === 61) {
                    $sawDefault = true;
                } elseif ($sawDefault && $jk === \__McTokId::T_VARIABLE && $s->kind[$j - 1] !== 61) {
                    // a variable after a default: a parameter name unless it sits inside the default expression (the real pass decides)
                    return true;
                }
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $l = $t->count();
        for ($i = 0; $i < $l; $i++) {
            if ($t->i[$i] === -1 || !($t->k[$i] === \__McTokId::T_FUNCTION || $t->k[$i] === \__McTokId::T_FN)) {
                continue;
            }
            $this->fixDefinition($t, $i);
        }
    }

    private function fixDefinition(FlatTokens $t, int $start): void
    {
        $open = self::nextOf($t, $start, 40);
        $close = $t->blockEnd($open, 41);
        $names = [];
        /** @var array<string, int[]> $nameIdx */
        $nameIdx = [];
        /** @var array<string, string> $default */
        $default = [];
        /** @var array<string, bool> $hasDefault */
        $hasDefault = [];
        /** @var array<string, string> $type */
        $type = [];
        /** @var array<string, bool> $hasType */
        $hasType = [];
        foreach (self::callArguments($t, $open, $close) as $a => $b) {
            $name = null;
            $idx = -1;
            $def = null;
            $ty = null;
            for ($q = $a; $q <= $b; $q++) {
                $k = $t->k[$q];
                if ($t->i[$q] !== -1 && $k === \__McTokId::T_ATTRIBUTE) {
                    $q = $t->blockEnd($q, CT::ATTRIBUTE_CLOSE);
                    continue;
                }
                if ($t->i[$q] !== -1 && $k === CT::PROPERTY_HOOK_BRACE_OPEN) {
                    $q = $t->blockEnd($q, CT::PROPERTY_HOOK_BRACE_CLOSE);
                    continue;
                }
                if ($k === FlatTokens::WS || $k === FlatTokens::COMMENT || $k === \__McTokId::T_ELLIPSIS || $k === \__McTokId::T_FINAL
                    || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE
                    || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET
                    || $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                    continue;
                }
                if ($k === \__McTokId::T_VARIABLE) {
                    if ($name !== null) {
                        continue;
                    }
                    $name = $t->content($q);
                    $idx = $q;
                    continue;
                }
                if ($k === 61) {
                    continue;
                }
                if ($name !== null) {
                    $def = ($def ?? '') . $t->content($q);
                } else {
                    $ty = ($ty ?? '') . $t->content($q);
                }
            }
            if ($name === null) {
                continue;
            }
            if (!isset($nameIdx[$name])) {
                $names[] = $name;
            }
            $nameIdx[$name] = [$idx];
            $hasDefault[$name] = $def !== null;
            $default[$name] = $def ?? '';
            $hasType[$name] = $ty !== null;
            $type[$name] = $ty ?? '';
        }
        $remove = false;
        for ($x = \count($names) - 1; $x >= 0; $x--) {
            $name = $names[$x];
            $nameIndex = $nameIdx[$name][0];
            $prevVar = $t->meaningful($nameIndex, -1);
            if (self::is($t, $prevVar, \__McTokId::T_ELLIPSIS)) {
                continue;
            }
            if (!$hasDefault[$name]) {
                $remove = true;
                continue;
            }
            if (!$remove) {
                continue;
            }
            if (\strtolower($default[$name]) === 'null' && $hasType[$name] && !self::nullable($type[$name])) {
                continue;
            }
            $this->removeDefault($t, $nameIndex, $this->defaultEnd($t, $nameIndex));
        }
    }

    private static function nullable(string $type): bool
    {
        if (\str_starts_with($type, '?')) {
            return true;
        }
        foreach (\explode('|', \strtolower($type)) as $p) {
            if (\trim($p) === 'null') {
                return true;
            }
        }
        return false;
    }

    private function defaultEnd(FlatTokens $t, int $index): int
    {
        while (($index = $t->meaningful($index, 1)) >= 0) {
            if ($t->k[$index] === 44 || $t->k[$index] === CT::PROPERTY_HOOK_BRACE_OPEN) {
                break;
            }
            $det = self::blockEdge($t->k[$index]);
            if ($det !== null && $det[1] === 1) {
                $index = $t->blockEnd($index, TypeWalk::closer($det[0]));
            }
        }
        return $t->meaningful($index < 0 ? $t->count() : $index, -1);
    }

    private function removeDefault(FlatTokens $t, int $start, int $end): void
    {
        for ($i = $t->meaningful($start, 1); $i <= $end;) {
            $prev = $t->nonEmptySibling($i, -1);
            if ($prev >= 0 && self::wsOnly($t, $prev, " \t\n\r\0\x0B")) {
                $pn = $t->nonWhitespace($prev, -1);
                if ($pn < 0 || !$t->isComment($pn)) {
                    self::clearMerge($t, $prev);
                }
            }
            self::clearMerge($t, $i);
            $i = $t->meaningful($i, 1);
            if ($i < 0) {
                break;
            }
        }
    }
}
