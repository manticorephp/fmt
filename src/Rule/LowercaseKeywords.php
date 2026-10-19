<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Casing\LowercaseKeywordsFixer (priority 0). Every token whose (custom) id is in
 * Token::getKeywords() except T_HALT_COMPILER gets strtolower(); a text rule, token count and kinds untouched.
 */
final class LowercaseKeywords extends GapRule
{
    /** @var array<int, bool> */
    private array $kw = [];
    /** @var array<int, bool> */
    private array $ctKw = [];

    public function __construct()
    {
        foreach ([\__McTokId::T_ABSTRACT, \__McTokId::T_ARRAY, \__McTokId::T_AS, \__McTokId::T_BREAK, \__McTokId::T_CALLABLE, \__McTokId::T_CASE,
            \__McTokId::T_CATCH, \__McTokId::T_CLASS, \__McTokId::T_CLONE, \__McTokId::T_CONST, \__McTokId::T_CONTINUE, \__McTokId::T_DECLARE,
            \__McTokId::T_DEFAULT, \__McTokId::T_DO, \__McTokId::T_ECHO, \__McTokId::T_ELSE, \__McTokId::T_ELSEIF, \__McTokId::T_EMPTY,
            \__McTokId::T_ENDDECLARE, \__McTokId::T_ENDFOR, \__McTokId::T_ENDFOREACH, \__McTokId::T_ENDIF, \__McTokId::T_ENDSWITCH,
            \__McTokId::T_ENDWHILE, \__McTokId::T_EVAL, \__McTokId::T_EXIT, \__McTokId::T_EXTENDS, \__McTokId::T_FINAL, \__McTokId::T_FINALLY,
            \__McTokId::T_FN, \__McTokId::T_FOR, \__McTokId::T_FOREACH, \__McTokId::T_FUNCTION, \__McTokId::T_GLOBAL, \__McTokId::T_GOTO,
            \__McTokId::T_IF, \__McTokId::T_IMPLEMENTS, \__McTokId::T_INCLUDE, \__McTokId::T_INCLUDE_ONCE, \__McTokId::T_INSTANCEOF,
            \__McTokId::T_INSTEADOF, \__McTokId::T_INTERFACE, \__McTokId::T_ISSET, \__McTokId::T_LIST, \__McTokId::T_LOGICAL_AND,
            \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR, \__McTokId::T_NAMESPACE, \__McTokId::T_NEW, \__McTokId::T_PRINT,
            \__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_REQUIRE, \__McTokId::T_REQUIRE_ONCE,
            \__McTokId::T_RETURN, \__McTokId::T_STATIC, \__McTokId::T_SWITCH, \__McTokId::T_THROW, \__McTokId::T_TRAIT, \__McTokId::T_TRY,
            \__McTokId::T_UNSET, \__McTokId::T_USE, \__McTokId::T_VAR, \__McTokId::T_WHILE, \__McTokId::T_YIELD, \__McTokId::T_YIELD_FROM,
            \__McTokId::T_ENUM, \__McTokId::T_MATCH, \__McTokId::T_PRIVATE_SET, \__McTokId::T_PROTECTED_SET, \__McTokId::T_PUBLIC_SET,
            \__McTokId::T_READONLY] as $k) {
            $this->kw[$k] = true;
        }
        foreach ([CT::ARRAY_TYPEHINT, CT::CLASS_CONSTANT, CT::CONST_IMPORT, CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE,
            CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED, CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC, CT::FUNCTION_IMPORT, CT::NAMESPACE_OPERATOR,
            CT::USE_LAMBDA, CT::USE_TRAIT] as $k) {
            $this->ctKw[$k] = true;
        }
    }

    public function name(): string
    {
        return 'lowercase_keywords';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function usesText(Source $s): bool
    {
        return true;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        // T_STRING: `Foo::CLASS` is a T_STRING lexed token that CT::CLASS_CONSTANT turns into a keyword
        $k = \array_keys($this->kw);
        $k[] = \__McTokId::T_STRING;
        return $k;
    }

    public function fixText(Source $s, int $i): ?string
    {
        $c = $s->ct[$i];
        if ($c !== 0 ? !isset($this->ctKw[$c]) : !isset($this->kw[$s->kind[$i]])) {
            return null;
        }
        $t = $s->text($i);
        $l = \strtolower($t);
        return $l === $t ? null : $l;
    }
}
