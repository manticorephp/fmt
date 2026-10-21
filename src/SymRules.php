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
use Fmt\Rule\NoEmptyStatement;
use Fmt\Rule\NoUnneededBraces;
use Fmt\Rule\EmptyLoopBody;
use Fmt\Rule\NoUselessNullsafeOperator;
use Fmt\Rule\CleanNamespace;
use Fmt\Rule\NoUselessReturn;
use Fmt\Rule\SwitchContinueToBreak;
use Fmt\Rule\IncludeRule;
use Fmt\Rule\EmptyLoopCondition;
use Fmt\Rule\SingleLineThrow;
use Fmt\Rule\NoNullPropertyInitialization;
use Fmt\Rule\SingleQuote;
use Fmt\Rule\SimpleToComplexStringVariable;
use Fmt\Rule\BacktickToShellExec;
use Fmt\Rule\NativeFunctionCasing;
use Fmt\Rule\ClassReferenceNameCasing;
use Fmt\Rule\NoUnsetCast;
use Fmt\Rule\StandardizeIncrement;
use Fmt\Rule\IncrementStyle;
use Fmt\Rule\ProtectedToPrivate;
use Fmt\Rule\NoUselessElse;
use Fmt\Rule\NoEmptyComment;
use Fmt\Rule\NoUnneededControlParentheses;
use Fmt\Rule\TypeDeclarationSpaces;
use Fmt\Rule\NoUselessConcatOperator;
use Fmt\Rule\BlankLineBeforeStatement;
use Fmt\Rule\LambdaNotUsedImport;
use Fmt\Rule\ClassAttributesSeparation;
use Fmt\Rule\YodaStyle;
use Fmt\Rule\NoAlternativeSyntax;
use Fmt\Rule\PhpUnitMethodCasing;
use Fmt\Rule\NullableTypeDeclarationForDefaultNullValue;
use Fmt\Rule\NoWhitespaceInEmptyArray;
use Fmt\Rule\MultilineCommentOpeningClosing;
use Fmt\Rule\CombineConsecutiveIssets;
use Fmt\Rule\CombineConsecutiveUnsets;
use Fmt\Rule\NoSuperfluousElseif;
use Fmt\Rule\ExplicitIndirectVariable;
use Fmt\Rule\ExplicitStringVariable;
use Fmt\Rule\HeredocToNowdoc;
use Fmt\Rule\StringImplicitBackslashes;
use Fmt\Rule\SelfStaticAccessor;
use Fmt\Rule\MethodChainingIndentation;
use Fmt\Rule\MultilineWhitespaceBeforeSemicolons;

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
        if ($name === 'no_empty_statement') {
            return new NoEmptyStatement();
        }
        if ($name === 'no_unneeded_braces') {
            Rules::only($name, $opt, ['namespaces']);
            return new NoUnneededBraces(Rules::bool($name, $opt, 'namespaces', false));
        }
        if ($name === 'empty_loop_body') {
            Rules::only($name, $opt, ['style']);
            return new EmptyLoopBody(Rules::enum($name, $opt, 'style', ['braces', 'semicolon'], 'semicolon') === 'braces');
        }
        if ($name === 'no_useless_nullsafe_operator') {
            return new NoUselessNullsafeOperator();
        }
        if ($name === 'clean_namespace') {
            return new CleanNamespace();
        }
        if ($name === 'no_useless_return') {
            return new NoUselessReturn();
        }
        if ($name === 'switch_continue_to_break') {
            return new SwitchContinueToBreak();
        }
        if ($name === 'include') {
            return new IncludeRule();
        }
        if ($name === 'empty_loop_condition') {
            Rules::only($name, $opt, ['style']);
            return new EmptyLoopCondition(Rules::enum($name, $opt, 'style', ['while', 'for'], 'while') === 'while');
        }
        if ($name === 'single_line_throw') {
            return new SingleLineThrow();
        }
        if ($name === 'no_null_property_initialization') {
            return new NoNullPropertyInitialization();
        }
        if ($name === 'single_quote') {
            Rules::only($name, $opt, ['strings_containing_single_quote_chars']);
            return new SingleQuote(Rules::bool($name, $opt, 'strings_containing_single_quote_chars', false));
        }
        if ($name === 'simple_to_complex_string_variable') {
            return new SimpleToComplexStringVariable();
        }
        if ($name === 'backtick_to_shell_exec') {
            return new BacktickToShellExec();
        }
        if ($name === 'native_function_casing') {
            return new NativeFunctionCasing();
        }
        if ($name === 'class_reference_name_casing') {
            return new ClassReferenceNameCasing();
        }
        if ($name === 'no_unset_cast') {
            return new NoUnsetCast();
        }
        if ($name === 'standardize_increment') {
            return new StandardizeIncrement();
        }
        if ($name === 'increment_style') {
            Rules::only($name, $opt, ['style']);
            return new IncrementStyle(Rules::enum($name, $opt, 'style', ['pre', 'post'], 'pre') === 'pre');
        }
        if ($name === 'protected_to_private') {
            return new ProtectedToPrivate();
        }
        if ($name === 'no_useless_else') {
            return new NoUselessElse();
        }
        if ($name === 'no_empty_comment') {
            return new NoEmptyComment();
        }
        if ($name === 'no_unneeded_control_parentheses') {
            Rules::only($name, $opt, ['statements']);
            $all = ['break', 'clone', 'continue', 'echo_print', 'negative_instanceof', 'others', 'return', 'switch_case', 'yield', 'yield_from'];
            return new NoUnneededControlParentheses(Rules::subset($name, $opt, 'statements', $all, ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield']));
        }
        if ($name === 'type_declaration_spaces') {
            Rules::only($name, $opt, ['elements']);
            $el = Rules::subset($name, $opt, 'elements', ['function', 'property', 'constant'], ['function', 'property']);
            return new TypeDeclarationSpaces(\in_array('function', $el, true), \in_array('property', $el, true), \in_array('constant', $el, true));
        }
        if ($name === 'no_useless_concat_operator') {
            Rules::only($name, $opt, ['juggle_simple_strings']);
            if (Rules::bool($name, $opt, 'juggle_simple_strings', false)) {
                throw new \InvalidArgumentException("$name: juggle_simple_strings=true is not implemented");
            }
            return new NoUselessConcatOperator();
        }
        if ($name === 'blank_line_before_statement') {
            Rules::only($name, $opt, ['statements']);
            $all = ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require',
                'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from'];
            return new BlankLineBeforeStatement(Rules::subset($name, $opt, 'statements', $all, ['break', 'continue', 'declare', 'return', 'throw', 'try']), $lineEnding);
        }
        if ($name === 'lambda_not_used_import') {
            return new LambdaNotUsedImport();
        }
        if ($name === 'class_attributes_separation') {
            Rules::only($name, $opt, ['elements']);
            $sp = ['const' => 'one', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none', 'case' => 'none'];
            if (isset($opt['elements'])) {
                if (!\is_array($opt['elements'])) {
                    throw new \InvalidArgumentException("$name: elements must be an object");
                }
                $sp = [];
                foreach ($opt['elements'] as $ty => $v) {
                    if (!\in_array($ty, ['const', 'method', 'property', 'trait_import', 'case'], true) || !\in_array($v, ['none', 'one', 'only_if_meta'], true)) {
                        throw new \InvalidArgumentException("$name: invalid element type or spacing");
                    }
                    $sp[(string) $ty] = (string) $v;
                }
            }
            return new ClassAttributesSeparation($sp, $lineEnding);
        }
        if ($name === 'yoda_style') {
            Rules::only($name, $opt, ['equal', 'identical', 'less_and_greater', 'always_move_variable']);
            if (Rules::bool($name, $opt, 'always_move_variable', false)) {
                throw new \InvalidArgumentException("$name: always_move_variable=true is not implemented");
            }
            $tri = static function (string $key, ?bool $def) use ($name, $opt): ?bool {
                if (!\array_key_exists($key, $opt)) {
                    return $def;
                }
                if ($opt[$key] !== null && !\is_bool($opt[$key])) {
                    throw new \InvalidArgumentException("$name: $key must be a bool or null");
                }
                return $opt[$key];
            };
            return new YodaStyle($tri('equal', true), $tri('identical', true), $tri('less_and_greater', null));
        }
        if ($name === 'no_alternative_syntax') {
            Rules::only($name, $opt, ['fix_non_monolithic_code']);
            return new NoAlternativeSyntax(Rules::bool($name, $opt, 'fix_non_monolithic_code', true));
        }
        if ($name === 'php_unit_method_casing') {
            Rules::only($name, $opt, ['case']);
            return new PhpUnitMethodCasing(Rules::enum($name, $opt, 'case', ['camel_case', 'snake_case'], 'camel_case') === 'camel_case');
        }
        if ($name === 'nullable_type_declaration_for_default_null_value') {
            Rules::only($name, $opt, ['use_nullable_type_declaration']);
            if (!Rules::bool($name, $opt, 'use_nullable_type_declaration', true)) {
                throw new \InvalidArgumentException("$name: use_nullable_type_declaration=false is not implemented");
            }
            return new NullableTypeDeclarationForDefaultNullValue();
        }
        if ($name === 'no_whitespace_in_empty_array') {
            return new NoWhitespaceInEmptyArray();
        }
        if ($name === 'multiline_comment_opening_closing') {
            return new MultilineCommentOpeningClosing();
        }
        if ($name === 'combine_consecutive_issets') {
            return new CombineConsecutiveIssets();
        }
        if ($name === 'combine_consecutive_unsets') {
            return new CombineConsecutiveUnsets();
        }
        if ($name === 'no_superfluous_elseif') {
            return new NoSuperfluousElseif();
        }
        if ($name === 'explicit_indirect_variable') {
            return new ExplicitIndirectVariable();
        }
        if ($name === 'explicit_string_variable') {
            return new ExplicitStringVariable();
        }
        if ($name === 'heredoc_to_nowdoc') {
            return new HeredocToNowdoc();
        }
        if ($name === 'string_implicit_backslashes') {
            Rules::only($name, $opt, ['single_quoted', 'double_quoted', 'heredoc']);
            return new StringImplicitBackslashes(Rules::enum($name, $opt, 'single_quoted', ['escape', 'unescape', 'ignore'], 'unescape'),
                Rules::enum($name, $opt, 'double_quoted', ['escape', 'unescape', 'ignore'], 'escape'),
                Rules::enum($name, $opt, 'heredoc', ['escape', 'unescape', 'ignore'], 'escape'));
        }
        if ($name === 'self_static_accessor') {
            return new SelfStaticAccessor();
        }
        if ($name === 'multiline_whitespace_before_semicolons') {
            Rules::only($name, $opt, ['strategy']);
            return new MultilineWhitespaceBeforeSemicolons(Rules::enum($name, $opt, 'strategy', ['no_multi_line', 'new_line_for_chained_calls'], 'no_multi_line') === 'new_line_for_chained_calls', $lineEnding);
        }
        if ($name === 'method_chaining_indentation') {
            return new MethodChainingIndentation($indent, $lineEnding);
        }
        return null;
    }
}
