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
use Fmt\Rule\NoSinglelineWhitespaceBeforeSemicolons;
use Fmt\Rule\ObjectOperatorWithoutWhitespace;
use Fmt\Rule\NoWhitespaceBeforeCommaInArray;
use Fmt\Rule\WhitespaceAfterCommaInArray;
use Fmt\Rule\TrimArraySpaces;
use Fmt\Rule\NoSpacesAroundOffset;
use Fmt\Rule\SpaceAfterSemicolon;

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
        if ($name === 'no_singleline_whitespace_before_semicolons') {
            return new NoSinglelineWhitespaceBeforeSemicolons();
        }
        if ($name === 'object_operator_without_whitespace') {
            return new ObjectOperatorWithoutWhitespace();
        }
        if ($name === 'no_whitespace_before_comma_in_array') {
            Rules::only($name, $opt, ['after_heredoc']);
            return new NoWhitespaceBeforeCommaInArray(Rules::bool($name, $opt, 'after_heredoc', false));
        }
        if ($name === 'whitespace_after_comma_in_array') {
            Rules::only($name, $opt, ['ensure_single_space']);
            return new WhitespaceAfterCommaInArray(Rules::bool($name, $opt, 'ensure_single_space', false));
        }
        if ($name === 'trim_array_spaces') {
            return new TrimArraySpaces();
        }
        if ($name === 'no_spaces_around_offset') {
            Rules::only($name, $opt, ['positions']);
            $pos = Rules::subset($name, $opt, 'positions', ['inside', 'outside'], ['inside', 'outside']);
            return new NoSpacesAroundOffset(\in_array('inside', $pos, true), \in_array('outside', $pos, true));
        }
        if ($name === 'space_after_semicolon') {
            Rules::only($name, $opt, ['remove_in_empty_for_expressions']);
            return new SpaceAfterSemicolon(Rules::bool($name, $opt, 'remove_in_empty_for_expressions', false));
        }

        return null;
    }
}
