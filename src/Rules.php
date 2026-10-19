<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\BinaryOperatorSpaces;
use Fmt\Rule\BracesPosition;
use Fmt\Rule\CastSpaces;
use Fmt\Rule\ConcatSpace;
use Fmt\Rule\IndentationType;
use Fmt\Rule\LineEnding;
use Fmt\Rule\NoBreakComment;
use Fmt\Rule\OperatorLinebreak;
use Fmt\Rule\FullOpeningTag;
use Fmt\Rule\Encoding;
use Fmt\Rule\BlankLineBetweenImportGroups;
use Fmt\Rule\NoMultipleStatementsPerLine;
use Fmt\Rule\ArrayIndentation;
use Fmt\Rule\SingleLineAfterImports;
use Fmt\Rule\ClassDefinition;
use Fmt\Rule\FunctionDeclaration;
use Fmt\Rule\BlankLineAfterNamespace;
use Fmt\Rule\BlankLinesBeforeNamespace;
use Fmt\Rule\BlankLineAfterOpeningTag;
use Fmt\Rule\NoBlankLinesAfterClassOpening;
use Fmt\Rule\ControlStructureContinuationPosition;
use Fmt\Rule\NoTrailingWhitespaceInComment;
use Fmt\Rule\SingleLineEmptyBody;
use Fmt\Rule\SwitchCaseSpace;
use Fmt\Rule\AttributeBlockNoSpaces;
use Fmt\Rule\TypesSpaces;
use Fmt\Rule\CompactNullableTypeDeclaration;
use Fmt\Rule\ReturnTypeDeclaration;
use Fmt\Rule\DeclareEqualNormalize;
use Fmt\Rule\SpacesInsideParentheses;
use Fmt\Rule\NoSpacesAfterFunctionName;
use Fmt\Rule\NoSpaceAroundDoubleColon;
use Fmt\Rule\SwitchCaseSemicolonToColon;
use Fmt\Rule\ConstantCase;
use Fmt\Rule\LowercaseStaticReference;
use Fmt\Rule\LowercaseCast;
use Fmt\Rule\LowercaseKeywords;
use Fmt\Rule\MethodArgumentSpace;
use Fmt\Rule\NoExtraBlankLines;
use Fmt\Rule\NoTrailingWhitespace;
use Fmt\Rule\NoWhitespaceInBlankLine;
use Fmt\Rule\SingleBlankLineAtEof;
use Fmt\Rule\SingleSpaceAroundConstruct;
use Fmt\Rule\StatementIndentation;
use Fmt\Rule\TernaryOperatorSpaces;
use Fmt\Rule\UnaryOperatorSpaces;
use Fmt\Rule\ElseIfMerge;
use Fmt\Rule\NoClosingTag;
use Fmt\Rule\NoLeadingImportSlash;
use Fmt\Rule\SingleTraitInsertPerStatement;
use Fmt\Rule\NewWithParentheses;

/**
 * Rule registry + presets. `--rules` is cs-fixer's: a csv of names/presets, or a JSON object
 * (`{"@PSR12": true, "no_extra_blank_lines": {"tokens": ["use"]}, "x": false}`).
 */
final class Rules
{
    public const PSR12 = 'binary_operator_spaces blank_line_after_opening_tag blank_line_between_import_groups blank_lines_before_namespace braces_position class_definition compact_nullable_type_declaration declare_equal_normalize lowercase_cast lowercase_static_reference modifier_keywords new_with_parentheses no_blank_lines_after_class_opening no_extra_blank_lines no_leading_import_slash no_whitespace_in_blank_line ordered_class_elements ordered_imports return_type_declaration short_scalar_cast single_import_per_statement single_space_around_construct single_trait_insert_per_statement ternary_operator_spaces unary_operator_spaces blank_line_after_namespace constant_case control_structure_braces control_structure_continuation_position elseif function_declaration indentation_type line_ending lowercase_keywords method_argument_space no_break_comment no_closing_tag no_multiple_statements_per_line no_space_around_double_colon no_spaces_after_function_name no_trailing_whitespace no_trailing_whitespace_in_comment single_blank_line_at_eof single_class_element_per_statement single_line_after_imports spaces_inside_parentheses statement_indentation switch_case_semicolon_to_colon switch_case_space encoding full_opening_tag';

    /** every rule name of @PSR12, @PER-CS2.0, @PER-CS3.0: `manticore-fmt list-rules` prints the implemented ones */
    public const KNOWN = self::PSR12 . ' array_indentation array_syntax attribute_block_no_spaces cast_spaces concat_space single_line_empty_body trailing_comma_in_multiline nullable_type_declaration operator_linebreak ordered_types types_spaces';

    public const BINARY_OPERATORS = ['=', '*', '/', '%', '<', '>', '|', '^', '+', '-', '&', '&=', '&&', '||', '.=', '/=', '=>', '==', '>=', '===',
        '!=', '<>', '!==', '<=', 'and', 'or', 'xor', '-=', '%=', '*=', '|=', '+=', '<<', '<<=', '>>', '>>=', '^=', '**', '**=', '<=>', '??', '??='];

    private static function spaceMode(string $name, mixed $v): int
    {
        if ($v === 'single_space') {
            return BinaryOperatorSpaces::SINGLE;
        }
        if ($v === 'at_least_single_space') {
            return BinaryOperatorSpaces::AT_LEAST;
        }
        if ($v === 'no_space') {
            return BinaryOperatorSpaces::NONE;
        }
        throw new \InvalidArgumentException($name . ': strategy ' . (\is_string($v) ? $v : 'value') . ' is not implemented (align* needs the alignment pass)');
    }

    /**
     * @param array<string, mixed> $opt
     * @throws \InvalidArgumentException on an unknown or invalid option
     */
    public static function make(string $name, string $lineEnding, array $opt = []): ?GapRule
    {
        if ($name === 'no_trailing_whitespace') {
            return new NoTrailingWhitespace();
        }
        if ($name === 'line_ending') {
            return new LineEnding($lineEnding);
        }
        if ($name === 'no_whitespace_in_blank_line') {
            return new NoWhitespaceInBlankLine($lineEnding);
        }
        if ($name === 'indentation_type') {
            return new IndentationType();
        }
        if ($name === 'single_blank_line_at_eof') {
            return new SingleBlankLineAtEof($lineEnding);
        }
        if ($name === 'no_extra_blank_lines') {
            self::only($name, $opt, ['tokens']);
            return new NoExtraBlankLines($lineEnding, isset($opt['tokens']) ? self::strList($name, $opt['tokens']) : ['extra']);
        }
        if ($name === 'braces_position') {
            $keys = ['control_structures_opening_brace', 'functions_opening_brace', 'anonymous_functions_opening_brace', 'classes_opening_brace',
                'anonymous_classes_opening_brace', 'allow_single_line_empty_anonymous_classes', 'allow_single_line_anonymous_functions'];
            self::only($name, $opt, $keys);
            $next = [];
            $defaults = [false, true, false, true, false];
            for ($k = 0; $k < 5; $k++) {
                $v = $opt[$keys[$k]] ?? null;
                if ($v === null) {
                    $next[] = $defaults[$k];
                } elseif ($v === 'same_line' || $v === 'next_line_unless_newline_at_signature_end') {
                    $next[] = $v !== 'same_line';
                } else {
                    throw new \InvalidArgumentException("$name: invalid value for {$keys[$k]}");
                }
            }
            return new BracesPosition($lineEnding, $next[0], $next[1], $next[2], $next[3], $next[4],
                self::bool($name, $opt, $keys[5], true), self::bool($name, $opt, $keys[6], true));
        }
        if ($name === 'binary_operator_spaces') {
            self::only($name, $opt, ['default', 'operators']);
            $modes = [];
            $def = \array_key_exists('default', $opt) ? $opt['default'] : 'single_space';
            if ($def !== null) {
                foreach (self::BINARY_OPERATORS as $op) {
                    $modes[$op] = self::spaceMode($name, $def);
                }
            }
            $ops = $opt['operators'] ?? [];
            if (!\is_array($ops)) {
                throw new \InvalidArgumentException("$name: operators must be an object");
            }
            foreach ($ops as $op => $v) {
                $op = (string) $op;
                if (!\in_array($op, self::BINARY_OPERATORS, true)) {
                    throw new \InvalidArgumentException("$name: unexpected operators key $op");
                }
                if ($v === null) {
                    unset($modes[$op]);
                } else {
                    $modes[$op] = self::spaceMode($name, $v);
                }
            }
            return new BinaryOperatorSpaces($modes);
        }
        if ($name === 'concat_space') {
            self::only($name, $opt, ['spacing']);
            return new ConcatSpace(self::enum($name, $opt, 'spacing', ['one', 'none'], 'none') === 'one');
        }
        if ($name === 'single_space_around_construct') {
            self::only($name, $opt, ['constructs_contain_a_single_space', 'constructs_followed_by_a_single_space', 'constructs_preceded_by_a_single_space']);
            $contain = self::subset($name, $opt, 'constructs_contain_a_single_space', ['yield_from'], ['yield_from']);
            $followed = self::subset($name, $opt, 'constructs_followed_by_a_single_space', self::CONSTRUCTS_FOLLOWED, self::CONSTRUCTS_FOLLOWED);
            $preceded = self::subset($name, $opt, 'constructs_preceded_by_a_single_space', ['as', 'else', 'elseif', 'use_lambda'], ['as', 'use_lambda']);
            return new SingleSpaceAroundConstruct($followed, $preceded, $contain !== []);
        }
        if ($name === 'method_argument_space') {
            self::only($name, $opt, ['keep_multiple_spaces_after_comma', 'on_multiline', 'after_heredoc', 'attribute_placement']);
            $om = self::enum($name, $opt, 'on_multiline', ['ignore', 'ensure_fully_multiline'], 'ensure_fully_multiline');
            $ap = self::enum($name, $opt, 'attribute_placement', ['ignore', 'standalone'], 'standalone');
            return new MethodArgumentSpace($lineEnding, self::bool($name, $opt, 'keep_multiple_spaces_after_comma', false), $om === 'ensure_fully_multiline',
                self::bool($name, $opt, 'after_heredoc', false), $ap === 'standalone');
        }
        if ($name === 'unary_operator_spaces') {
            self::only($name, $opt, ['only_dec_inc']);
            return new UnaryOperatorSpaces(self::bool($name, $opt, 'only_dec_inc', false));
        }
        if ($name === 'ternary_operator_spaces') {
            return new TernaryOperatorSpaces();
        }
        if ($name === 'cast_spaces') {
            self::only($name, $opt, ['space']);
            return new CastSpaces(self::enum($name, $opt, 'space', ['single', 'none'], 'single') === 'single');
        }
        if ($name === 'lowercase_keywords') {
            return new LowercaseKeywords();
        }
        if ($name === 'lowercase_cast' || $name === 'short_scalar_cast') {
            return new LowercaseCast($name === 'short_scalar_cast');
        }
        if ($name === 'lowercase_static_reference') {
            return new LowercaseStaticReference();
        }
        if ($name === 'constant_case') {
            self::only($name, $opt, ['case']);
            return new ConstantCase(self::enum($name, $opt, 'case', ['upper', 'lower'], 'lower') === 'upper');
        }
        if ($name === 'switch_case_semicolon_to_colon') {
            return new SwitchCaseSemicolonToColon();
        }
        if ($name === 'no_space_around_double_colon') {
            return new NoSpaceAroundDoubleColon();
        }
        if ($name === 'no_spaces_after_function_name') {
            return new NoSpacesAfterFunctionName();
        }
        if ($name === 'spaces_inside_parentheses') {
            self::only($name, $opt, ['space']);
            if (self::enum($name, $opt, 'space', ['none', 'single'], 'none') === 'single') {
                throw new \InvalidArgumentException("$name: space=single is not implemented");
            }
            return new SpacesInsideParentheses();
        }
        if ($name === 'declare_equal_normalize') {
            self::only($name, $opt, ['space']);
            if (self::enum($name, $opt, 'space', ['single', 'none'], 'none') === 'single') {
                throw new \InvalidArgumentException("$name: space=single is not implemented");
            }
            return new DeclareEqualNormalize();
        }
        if ($name === 'return_type_declaration') {
            self::only($name, $opt, ['space_before']);
            return new ReturnTypeDeclaration(self::enum($name, $opt, 'space_before', ['one', 'none'], 'none') === 'one');
        }
        if ($name === 'compact_nullable_type_declaration') {
            return new CompactNullableTypeDeclaration();
        }
        if ($name === 'types_spaces') {
            self::only($name, $opt, ['space', 'space_multiple_catch']);
            $sp = self::enum($name, $opt, 'space', ['none', 'single'], 'none');
            if (isset($opt['space_multiple_catch']) && $opt['space_multiple_catch'] !== $sp) {
                throw new \InvalidArgumentException("$name: space_multiple_catch different from space is not implemented");
            }
            return new TypesSpaces($sp === 'single');
        }
        if ($name === 'attribute_block_no_spaces') {
            return new AttributeBlockNoSpaces();
        }
        if ($name === 'switch_case_space') {
            return new SwitchCaseSpace();
        }
        if ($name === 'single_line_empty_body') {
            return new SingleLineEmptyBody();
        }
        if ($name === 'no_trailing_whitespace_in_comment') {
            return new NoTrailingWhitespaceInComment();
        }
        if ($name === 'control_structure_continuation_position') {
            self::only($name, $opt, ['position']);
            if (self::enum($name, $opt, 'position', ['same_line', 'next_line'], 'same_line') === 'next_line') {
                throw new \InvalidArgumentException("$name: position=next_line is not implemented");
            }
            return new ControlStructureContinuationPosition();
        }
        if ($name === 'no_blank_lines_after_class_opening') {
            return new NoBlankLinesAfterClassOpening();
        }
        if ($name === 'blank_line_after_opening_tag') {
            return new BlankLineAfterOpeningTag();
        }
        if ($name === 'blank_lines_before_namespace') {
            self::only($name, $opt, ['min_line_breaks', 'max_line_breaks']);
            $min = $opt['min_line_breaks'] ?? 2;
            $max = $opt['max_line_breaks'] ?? 2;
            if (!\is_int($min) || !\is_int($max) || $min < 0 || $max < $min) {
                throw new \InvalidArgumentException("$name: invalid min_line_breaks / max_line_breaks");
            }
            return new BlankLinesBeforeNamespace($min, $max);
        }
        if ($name === 'blank_line_after_namespace') {
            return new BlankLineAfterNamespace();
        }
        if ($name === 'function_declaration') {
            self::only($name, $opt, ['closure_function_spacing', 'closure_fn_spacing', 'trailing_comma_single_line']);
            return new FunctionDeclaration(self::enum($name, $opt, 'closure_function_spacing', ['one', 'none'], 'one') === 'one',
                self::enum($name, $opt, 'closure_fn_spacing', ['one', 'none'], 'one') === 'one', self::bool($name, $opt, 'trailing_comma_single_line', false));
        }
        if ($name === 'class_definition') {
            self::only($name, $opt, ['multi_line_extends_each_single_line', 'single_item_single_line', 'single_line', 'space_before_parenthesis', 'inline_constructor_arguments']);
            return new ClassDefinition(self::bool($name, $opt, 'single_line', false), self::bool($name, $opt, 'single_item_single_line', false),
                self::bool($name, $opt, 'multi_line_extends_each_single_line', false), self::bool($name, $opt, 'space_before_parenthesis', false),
                self::bool($name, $opt, 'inline_constructor_arguments', true));
        }
        if ($name === 'single_line_after_imports') {
            return new SingleLineAfterImports();
        }
        if ($name === 'array_indentation') {
            return new ArrayIndentation();
        }
        if ($name === 'no_multiple_statements_per_line') {
            return new NoMultipleStatementsPerLine();
        }
        if ($name === 'blank_line_between_import_groups') {
            return new BlankLineBetweenImportGroups();
        }
        if ($name === 'encoding') {
            return new Encoding();
        }
        if ($name === 'full_opening_tag') {
            return new FullOpeningTag();
        }
        if ($name === 'operator_linebreak') {
            self::only($name, $opt, ['only_booleans', 'position']);
            return new OperatorLinebreak(self::bool($name, $opt, 'only_booleans', false), self::enum($name, $opt, 'position', ['beginning', 'end'], 'beginning') === 'beginning');
        }
        if ($name === 'no_break_comment') {
            self::only($name, $opt, ['comment_text']);
            $txt = $opt['comment_text'] ?? 'no break';
            if (!\is_string($txt) || \preg_match('/\R/', $txt) === 1) {
                throw new \InvalidArgumentException("$name: comment_text must be a string without line breaks");
            }
            return new NoBreakComment($txt);
        }
        if ($name === 'elseif') {
            return new ElseIfMerge();
        }
        if ($name === 'no_closing_tag') {
            return new NoClosingTag();
        }
        if ($name === 'no_leading_import_slash') {
            return new NoLeadingImportSlash();
        }
        if ($name === 'single_trait_insert_per_statement') {
            return new SingleTraitInsertPerStatement();
        }
        if ($name === 'new_with_parentheses') {
            self::only($name, $opt, ['named_class', 'anonymous_class']);
            return new NewWithParentheses(self::bool($name, $opt, 'named_class', true), self::bool($name, $opt, 'anonymous_class', true));
        }
        if ($name === 'statement_indentation') {
            self::only($name, $opt, ['stick_comment_to_next_continuous_control_statement']);
            return new StatementIndentation($lineEnding, self::bool($name, $opt, 'stick_comment_to_next_continuous_control_statement', false));
        }
        return null;
    }

    public const CONSTRUCTS_FOLLOWED = ['abstract', 'as', 'attribute', 'break', 'case', 'catch', 'class', 'clone', 'comment', 'const', 'const_import',
        'continue', 'do', 'echo', 'else', 'elseif', 'enum', 'extends', 'final', 'finally', 'for', 'foreach', 'function', 'function_import', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'match', 'named_argument', 'namespace', 'new',
        'open_tag_with_echo', 'php_doc', 'php_open', 'print', 'private', 'private_set', 'protected', 'protected_set', 'public', 'public_set', 'readonly',
        'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'type_colon', 'use', 'use_lambda', 'use_trait', 'var', 'while',
        'yield', 'yield_from'];

    /**
     * @param array<string, mixed> $opt
     * @param string[] $allowed
     * @param string[] $default
     * @return string[]
     */
    private static function subset(string $name, array $opt, string $key, array $allowed, array $default): array
    {
        if (!isset($opt[$key])) {
            return $default;
        }
        $out = self::strList($name, $opt[$key]);
        foreach ($out as $x) {
            if (!\in_array($x, $allowed, true)) {
                throw new \InvalidArgumentException("$name: invalid value $x for $key");
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $opt
     * @param string[] $keys
     */
    private static function only(string $name, array $opt, array $keys): void
    {
        foreach ($opt as $k => $v) {
            if (!\in_array($k, $keys, true)) {
                throw new \InvalidArgumentException("$name: unknown option $k");
            }
        }
    }

    /**
     * @param array<string, mixed> $opt
     * @param string[] $allowed
     */
    private static function enum(string $name, array $opt, string $key, array $allowed, string $default): string
    {
        if (!isset($opt[$key])) {
            return $default;
        }
        $v = $opt[$key];
        if (!\is_string($v) || !\in_array($v, $allowed, true)) {
            throw new \InvalidArgumentException("$name: invalid value for $key");
        }
        return $v;
    }

    /** @param array<string, mixed> $opt */
    private static function bool(string $name, array $opt, string $key, bool $default): bool
    {
        if (!isset($opt[$key])) {
            return $default;
        }
        $v = $opt[$key];
        if (!\is_bool($v)) {
            throw new \InvalidArgumentException("$name: $key must be a bool");
        }
        return $v;
    }

    /** @return string[] */
    private static function strList(string $name, mixed $v): array
    {
        if (!\is_array($v)) {
            throw new \InvalidArgumentException("$name: expected a list of strings");
        }
        $out = [];
        foreach ($v as $x) {
            if (!\is_string($x)) {
                throw new \InvalidArgumentException("$name: expected a list of strings");
            }
            $out[] = $x;
        }
        return $out;
    }

    /**
     * Options a preset gives a rule (cs-fixer's RuleSet definitions; @PSR12 overrides @PSR2).
     * @return array<string, mixed>
     */
    private static function presetOptions(string $rule): array
    {
        if ($rule === 'braces_position') {
            return ['allow_single_line_anonymous_functions' => false, 'allow_single_line_empty_anonymous_classes' => true];
        }
        if ($rule === 'single_space_around_construct') {
            return ['constructs_followed_by_a_single_space' => ['abstract', 'as', 'case', 'catch', 'class', 'const_import', 'do', 'else', 'elseif', 'final',
                'finally', 'for', 'foreach', 'function', 'function_import', 'if', 'insteadof', 'interface', 'namespace', 'new', 'private', 'protected', 'public',
                'static', 'switch', 'trait', 'try', 'use', 'use_lambda', 'while'], 'constructs_preceded_by_a_single_space' => ['as', 'else', 'elseif', 'use_lambda']];
        }
        if ($rule === 'binary_operator_spaces') {
            return ['default' => 'at_least_single_space'];
        }
        if ($rule === 'class_definition') {
            return ['inline_constructor_arguments' => false, 'space_before_parenthesis' => true];
        }
        if ($rule === 'unary_operator_spaces') {
            return ['only_dec_inc' => true];
        }
        if ($rule === 'function_declaration') {
            return ['closure_fn_spacing' => 'one'];
        }
        if ($rule === 'method_argument_space') {
            return ['after_heredoc' => false, 'attribute_placement' => 'ignore', 'on_multiline' => 'ensure_fully_multiline'];
        }
        if ($rule === 'no_extra_blank_lines') {
            return ['tokens' => ['use']];
        }
        if ($rule === 'new_with_parentheses') {
            return ['anonymous_class' => true];
        }
        return [];
    }

    /**
     * @return array<string, array<string, mixed>> rule name => options, in the given order (presets expanded in place,
     *         a later entry replaces an earlier one, `false` in JSON removes the rule)
     * @throws \InvalidArgumentException on a malformed JSON rule set
     */
    public static function resolve(string $arg): array
    {
        /** @var array<string, array<string, mixed>> $out */
        $out = [];
        $arg = \trim($arg);
        if ($arg !== '' && $arg[0] === '{') {
            $j = \json_decode($arg, true);
            if (!\is_array($j)) {
                throw new \InvalidArgumentException('--rules: invalid JSON');
            }
            foreach ($j as $name => $v) {
                $name = (string) $name;
                if ($v === false) {
                    unset($out[$name]);
                    continue;
                }
                if ($name === '@PSR12') {
                    self::addPreset($out);
                    continue;
                }
                /** @var array<string, mixed> $opt */
                $opt = [];
                if (\is_array($v)) {
                    foreach ($v as $k => $x) {
                        $opt[(string) $k] = $x;
                    }
                } elseif ($v !== true) {
                    throw new \InvalidArgumentException("--rules: $name must be true, false or an object");
                }
                $out[$name] = $opt;
            }
            return $out;
        }
        foreach (\explode(',', $arg) as $item) {
            $item = \trim($item);
            if ($item === '') {
                continue;
            }
            if ($item === '@PSR12') {
                self::addPreset($out);
            } elseif ($item[0] === '-') {
                unset($out[\substr($item, 1)]);
            } else {
                $out[$item] = [];
            }
        }
        return $out;
    }

    /** @param array<string, array<string, mixed>> $out */
    private static function addPreset(array &$out): void
    {
        foreach (\explode(' ', self::PSR12) as $nm) {
            $out[$nm] = self::presetOptions($nm);
        }
    }
}
