<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\SetTypeToCastFixer (priority 0, risky). `settype($a, "int");` -> `$a = (int) $a;` (`"null"` -> `$a = null;`); only a statement
 * of its own, a plain variable and a plain string literal. Whole-file FlatTokens port, last call first.
 */
final class SetTypeToCast extends FlatRule
{
    public function name(): string
    {
        return 'set_type_to_cast';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        return self::hasCallOf($s, ['settype']);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $map = ['array' => [\__McTokId::T_ARRAY_CAST, '(array)'], 'bool' => [\__McTokId::T_BOOL_CAST, '(bool)'], 'boolean' => [\__McTokId::T_BOOL_CAST, '(bool)'],
            'double' => [\__McTokId::T_DOUBLE_CAST, '(float)'], 'float' => [\__McTokId::T_DOUBLE_CAST, '(float)'], 'int' => [\__McTokId::T_INT_CAST, '(int)'],
            'integer' => [\__McTokId::T_INT_CAST, '(int)'], 'object' => [\__McTokId::T_OBJECT_CAST, '(object)'], 'string' => [\__McTokId::T_STRING_CAST, '(string)']];
        $cands = [];
        $end = $t->count();
        for ($i = 1; $i < $end; $i++) {
            $c = $this->findCall($s, $t, 'settype', $i, $end);
            if ($c === null) {
                break;
            }
            $i = $c[1];
            $cands[] = $c;
        }
        for ($ci = \count($cands) - 1; $ci >= 0; $ci--) {
            [$fn, $open, $close] = $cands[$ci];
            $args = self::callArguments($t, $open, $close);
            if (\count($args) !== 2) {
                continue;
            }
            $prev = $t->meaningful($fn, -1);
            if (!(self::is($t, $prev, 59) || self::is($t, $prev, 123) || self::is($t, $prev, 125) || self::is($t, $prev, \__McTokId::T_OPEN_TAG))) {
                continue;
            }
            $starts = \array_keys($args);
            $firstStart = $starts[0];
            if ($t->k[$firstStart] === FlatTokens::WS || $t->k[$firstStart] === FlatTokens::COMMENT) {
                $firstStart = $t->meaningful($firstStart, 1);
            }
            if (!self::is($t, $firstStart, \__McTokId::T_VARIABLE)) {
                continue;
            }
            $comma = $t->meaningful($firstStart, 1);
            if (!self::is($t, $comma, 44)) {
                continue;
            }
            $secondStart = $starts[1];
            $secondEnd = $args[$secondStart];
            if ($t->k[$secondStart] === FlatTokens::WS || $t->k[$secondStart] === FlatTokens::COMMENT) {
                $secondStart = $t->meaningful($secondStart, 1);
            }
            if (!self::is($t, $secondStart, \__McTokId::T_CONSTANT_ENCAPSED_STRING) || $t->meaningful($secondStart, 1) < $secondEnd) {
                continue;
            }
            $type = \strtolower(\trim($t->content($secondStart), '"\''));
            if ($type !== 'null' && !isset($map[$type])) {
                continue;
            }
            $var = $t->content($firstStart);
            self::clearMerge($t, $close);
            $pi = $t->meaningful($close, -1);
            if (self::is($t, $pi, 44)) {
                self::clearMerge($t, $pi);
            }
            self::clearMerge($t, $secondStart);
            self::clearMerge($t, $comma);
            self::clearMerge($t, $firstStart);
            self::clearMerge($t, $open);
            $t->clearAt($fn);
            self::clearEmpty($t);
            if ($type === 'null') {
                $t->insertAt($fn, [FlatTokens::code(\__McTokId::T_VARIABLE, $var), FlatTokens::ws(' '), FlatTokens::code(61, '='), FlatTokens::ws(' '),
                    FlatTokens::code(\__McTokId::T_STRING, 'null')]);
                self::removeWs($t, $fn + 4, 1);
            } else {
                $t->insertAt($fn, [FlatTokens::code(\__McTokId::T_VARIABLE, $var), FlatTokens::ws(' '), FlatTokens::code(61, '='), FlatTokens::ws(' '),
                    FlatTokens::code($map[$type][0], $map[$type][1]), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_VARIABLE, $var)]);
                self::removeWs($t, $fn + 6, 1);
            }
        }
    }
}
