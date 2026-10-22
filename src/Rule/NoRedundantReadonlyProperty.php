<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\NoRedundantReadonlyPropertyFixer (priority 3). In a `readonly class` every property / promoted property
 * loses its own `readonly` (found walking back over modifiers, type tokens and `&`), with the whitespace behind it. Whole-file FlatTokens port.
 */
final class NoRedundantReadonlyProperty extends FlatRule
{
    public function name(): string
    {
        return 'no_redundant_readonly_property';
    }

    public function priority(): int
    {
        return 3;
    }

    protected function candidate(Source $s): bool
    {
        $ro = false;
        $cl = false;
        for ($i = 0; $i < $s->n; $i++) {
            $cl = $cl || $s->kind[$i] === \__McTokId::T_CLASS;
            $ro = $ro || $s->kind[$i] === \__McTokId::T_READONLY;
        }
        return $cl && $ro;
    }

    private static function expected(int $k): bool
    {
        return $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR || $k === CT::NULLABLE_TYPE || $k === CT::ARRAY_TYPEHINT || $k === CT::TYPE_ALTERNATION
            || $k === CT::TYPE_INTERSECTION || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE
            || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC
            || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_VAR || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED
            || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET
            || $k === \__McTokId::T_PUBLIC_SET;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        foreach (Analyzer::classyElements($s) as $idx => [$type, $cls]) {
            if ($type !== Analyzer::E_PROPERTY && $type !== Analyzer::E_PROMOTED) {
                continue;
            }
            // getClassyModifiers: is the class `readonly`?
            $q = $t->pos[$cls];
            $readonlyClass = false;
            while (true) {
                $q = $t->meaningful($q, -1);
                if ($q < 0) {
                    break;
                }
                $k = $t->k[$q];
                if ($k === \__McTokId::T_FINAL || $k === \__McTokId::T_ABSTRACT) {
                    continue;
                }
                if ($k === \__McTokId::T_READONLY) {
                    $readonlyClass = true;
                    continue;
                }
                break;
            }
            if (!$readonlyClass) {
                continue;
            }
            $readonly = -1;
            $prev = $t->meaningful($t->pos[$idx], -1);
            while ($prev >= 0 && (self::expected($t->k[$prev]) || self::is($t, $prev, 38))) {
                if (self::is($t, $prev, \__McTokId::T_READONLY)) {
                    $readonly = $prev;
                    break;
                }
                $prev = $t->meaningful($prev, -1);
            }
            if ($readonly >= 0) {
                self::removeWs($t, $readonly, 1);
                $t->clearAt($readonly);
            }
        }
    }
}
