<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\IntegerLiteralCase;
use Fmt\Rule\LinebreakAfterOpeningTag;
use Fmt\Rule\MagicConstantCasing;
use Fmt\Rule\MagicMethodCasing;
use Fmt\Rule\NativeTypeDeclarationCasing;
use Fmt\Rule\NoAliasLanguageConstructCall;
use Fmt\Rule\NoBinaryString;
use Fmt\Rule\NoLeadingNamespaceWhitespace;
use Fmt\Rule\NoShortBoolCast;
use Fmt\Rule\StandardizeNotEquals;

/** Registry branch of the @Symfony rules that are not phpdoc (Rules::make falls through to it; same contract). */
final class SymRules
{
    /**
     * @param array<string, mixed> $opt
     * @throws \InvalidArgumentException on an unknown or invalid option
     */
    public static function make(string $name, string $lineEnding, array $opt = [], string $indent = '    '): ?GapRule
    {
        if ($name === 'magic_constant_casing') {
            return new MagicConstantCasing();
        }
        if ($name === 'integer_literal_case') {
            return new IntegerLiteralCase();
        }
        if ($name === 'magic_method_casing') {
            return new MagicMethodCasing();
        }
        if ($name === 'native_type_declaration_casing') {
            return new NativeTypeDeclarationCasing();
        }
        if ($name === 'no_alias_language_construct_call') {
            return new NoAliasLanguageConstructCall();
        }
        if ($name === 'standardize_not_equals') {
            return new StandardizeNotEquals();
        }
        if ($name === 'linebreak_after_opening_tag') {
            return new LinebreakAfterOpeningTag($lineEnding);
        }
        if ($name === 'no_leading_namespace_whitespace') {
            return new NoLeadingNamespaceWhitespace($lineEnding);
        }
        if ($name === 'no_short_bool_cast') {
            return new NoShortBoolCast();
        }
        if ($name === 'no_binary_string') {
            return new NoBinaryString();
        }
        return null;
    }
}
