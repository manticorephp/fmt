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
use Fmt\Rule\DeclareParentheses;
use Fmt\Rule\NormalizeIndexBrace;
use Fmt\Rule\NoUnneededImportAlias;
use Fmt\Rule\SingleLineCommentSpacing;
use Fmt\Rule\NoMultilineWhitespaceAroundDoubleArrow;
use Fmt\Rule\NoTrailingCommaInSingleline;
use Fmt\Rule\SemicolonAfterInstruction;
use Fmt\Rule\EchoTagSyntax;
use Fmt\Rule\NoMixedEchoPrint;
use Fmt\Rule\SingleLineCommentStyle;

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

        if ($name === 'declare_parentheses') {
            return new DeclareParentheses();
        }
        if ($name === 'normalize_index_brace') {
            return new NormalizeIndexBrace();
        }
        if ($name === 'no_unneeded_import_alias') {
            return new NoUnneededImportAlias();
        }
        if ($name === 'single_line_comment_spacing') {
            return new SingleLineCommentSpacing();
        }
        if ($name === 'no_multiline_whitespace_around_double_arrow') {
            return new NoMultilineWhitespaceAroundDoubleArrow();
        }
        if ($name === 'no_trailing_comma_in_singleline') {
            Rules::only($name, $opt, ['elements']);
            $el = Rules::subset($name, $opt, 'elements', ['arguments', 'array', 'array_destructuring', 'group_import'], ['arguments', 'array', 'array_destructuring', 'group_import']);
            return new NoTrailingCommaInSingleline(\in_array('arguments', $el, true), \in_array('array', $el, true), \in_array('array_destructuring', $el, true),
                \in_array('group_import', $el, true));
        }
        if ($name === 'semicolon_after_instruction') {
            return new SemicolonAfterInstruction();
        }
        if ($name === 'echo_tag_syntax') {
            Rules::only($name, $opt, ['format', 'long_function', 'shorten_simple_statements_only']);
            if (Rules::enum($name, $opt, 'format', ['long', 'short'], 'long') === 'short') {
                throw new \InvalidArgumentException("$name: format=short is not implemented");
            }
            Rules::bool($name, $opt, 'shorten_simple_statements_only', true);
            return new EchoTagSyntax(Rules::enum($name, $opt, 'long_function', ['echo', 'print'], 'echo'));
        }
        if ($name === 'no_mixed_echo_print') {
            Rules::only($name, $opt, ['use']);
            return new NoMixedEchoPrint(Rules::enum($name, $opt, 'use', ['print', 'echo'], 'echo') === 'print');
        }
        if ($name === 'single_line_comment_style') {
            Rules::only($name, $opt, ['comment_types']);
            $ct = Rules::subset($name, $opt, 'comment_types', ['asterisk', 'hash'], ['asterisk', 'hash']);
            return new SingleLineCommentStyle(\in_array('asterisk', $ct, true), \in_array('hash', $ct, true));
        }
        return null;
    }
}
