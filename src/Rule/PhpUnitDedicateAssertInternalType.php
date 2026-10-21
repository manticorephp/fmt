<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertInternalTypeFixer (priority -16, risky). `assertInternalType('array', $x)` -> `assertIsArray($x)` (and assertNotInternalType ->
 * assertIsNotArray / assertNotNull); no same-class test, anonymous classes are skipped. Whole-file FlatTokens port.
 */
final class PhpUnitDedicateAssertInternalType extends PhpUnitClassRule
{
    public function name(): string
    {
        return 'php_unit_dedicate_assert_internal_type';
    }

    public function priority(): int
    {
        return -16;
    }

    private static function assertion(string $type): string
    {
        return match ($type) {
            'array' => 'assertIsArray',
            'boolean', 'bool' => 'assertIsBool',
            'double', 'float', 'real' => 'assertIsFloat',
            'integer', 'int' => 'assertIsInt',
            'null' => 'assertNull',
            'numeric' => 'assertIsNumeric',
            'object' => 'assertIsObject',
            'resource' => 'assertIsResource',
            'string' => 'assertIsString',
            'scalar' => 'assertIsScalar',
            'callable' => 'assertIsCallable',
            'iterable' => 'assertIsIterable',
            default => '',
        };
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        /** @var array<int, int> $anon closing brace => opening brace */
        $anon = [];
        for ($index = $start; $index < $end; $index++) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_CLASS || !self::isAnonymousClass($t, $index)) {
                continue;
            }
            $open = self::nextOf($t, $index, 123);
            $anon[$t->blockEnd($open, 125)] = $open;
        }
        for ($index = $end - 1; $index > $start; $index--) {
            if (isset($anon[$index])) {
                $index = $anon[$index];
                continue;
            }
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_STRING) {
                continue;
            }
            $fn = \strtolower($t->content($index));
            if ($fn !== 'assertinternaltype' && $fn !== 'assertnotinternaltype') {
                continue;
            }
            $br = $t->meaningful($index, 1);
            if (!self::is($t, $br, 40)) {
                continue;
            }
            $ti = $t->meaningful($br, 1);
            if (!self::is($t, $ti, \__McTokId::T_CONSTANT_ENCAPSED_STRING)) {
                continue;
            }
            $type = \trim($t->content($ti), '\'"');
            $new = self::assertion($type);
            if ($new === '') {
                continue;
            }
            $comma = $t->meaningful($ti, 1);
            if (!self::is($t, $comma, 44)) {
                continue;
            }
            if ($fn === 'assertnotinternaltype') {
                $new = \str_replace('Is', 'IsNot', $new);
                $new = \str_replace('Null', 'NotNull', $new);
            }
            $next = $t->meaningful($comma, 1);
            self::overrideRange($t, $index, $next - 1, [FlatTokens::code(\__McTokId::T_STRING, $new), FlatTokens::code(40, '(')]);
        }
    }
}
