<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\MagicConstantCasingFixer (priority 0). `__LINE__` ... `__TRAIT__` and the `class` of `Foo::class`
 * get their canonical case. Text rule.
 */
final class MagicConstantCasing extends TextRule
{
    /** @var array<int, string> */
    private array $names = [];

    public function __construct()
    {
        $this->names = [\__McTokId::T_LINE => '__LINE__', \__McTokId::T_FILE => '__FILE__', \__McTokId::T_DIR => '__DIR__',
            \__McTokId::T_FUNC_C => '__FUNCTION__', \__McTokId::T_CLASS_C => '__CLASS__', \__McTokId::T_METHOD_C => '__METHOD__',
            \__McTokId::T_NS_C => '__NAMESPACE__', \__McTokId::T_TRAIT_C => '__TRAIT__', \__McTokId::T_PROPERTY_C => '__PROPERTY__'];
    }

    public function name(): string
    {
        return 'magic_constant_casing';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_LINE, \__McTokId::T_FILE, \__McTokId::T_DIR, \__McTokId::T_FUNC_C, \__McTokId::T_CLASS_C, \__McTokId::T_METHOD_C,
            \__McTokId::T_NS_C, \__McTokId::T_TRAIT_C, \__McTokId::T_PROPERTY_C, \__McTokId::T_CLASS, \__McTokId::T_STRING];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_STRING) {
            return $s->ct[$i] === CT::CLASS_CONSTANT && $s->text($i) !== 'class' ? 'class' : null;
        }
        if (!isset($this->names[$k]) || $s->ct[$i] !== 0) {
            return null;
        }
        $t = $this->names[$k];
        return $s->text($i) === $t ? null : $t;
    }
}
