<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\NativeTypeDeclarationCasingFixer (priority 0). A native type name (`INT`, `Callable`, `SELF` ...)
 * in a type position (after `const`, `?`, `|`, the return-type colon; or before a variable or `|`) is lowercased. Text rule.
 */
final class NativeTypeDeclarationCasing extends TextRule
{
    private const TYPES = ['array' => 1, 'bool' => 1, 'callable' => 1, 'float' => 1, 'int' => 1, 'iterable' => 1, 'object' => 1, 'parent' => 1,
        'self' => 1, 'static' => 1, 'string' => 1, 'void' => 1, 'false' => 1, 'mixed' => 1, 'null' => 1, 'never' => 1, 'true' => 1];

    public function name(): string
    {
        return 'native_type_declaration_casing';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_STRING, \__McTokId::T_ARRAY, \__McTokId::T_CALLABLE, \__McTokId::T_STATIC];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $w = $s->end[$i] - $s->start[$i];
        if ($w < 3 || $w > 8 || $i === 0) {
            return null;
        }
        $t = $s->text($i);
        $l = \strtolower($t);
        if ($l === $t || !isset(self::TYPES[$l])) {
            return null;
        }
        $p = $i - 1;
        $pk = $s->kind[$p];
        $pc = $s->ct[$p];
        if ($pc === 0 && ($pk === 61 || $pk === \__McTokId::T_CASE || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_DOUBLE_COLON
            || $pk === \__McTokId::T_NS_SEPARATOR)) {
            return null;
        }
        if ($i + 1 >= $s->n) {
            return null;
        }
        $n = $i + 1;
        $nk = $s->kind[$n];
        $nc = $s->ct[$n];
        if ($nc === 0 && ($nk === 61 || $nk === \__McTokId::T_NS_SEPARATOR)) {
            return null;
        }
        if (!(($pc === 0 && $pk === \__McTokId::T_CONST) || $pc === CT::NULLABLE_TYPE || $pc === CT::TYPE_ALTERNATION || $pc === CT::TYPE_COLON)
            && !(($nc === 0 && $nk === \__McTokId::T_VARIABLE) || $nc === CT::TYPE_ALTERNATION)) {
            return null;
        }
        return $l;
    }
}
