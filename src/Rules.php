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
use Fmt\Rule\NoUnusedImports;
use Fmt\Rule\OrderedClassElements;
use Fmt\Rule\OrderedImports;
use Fmt\Rule\OrderedTypes;
use Fmt\Rule\NullableTypeDeclaration;
use Fmt\Rule\ArraySyntax;
use Fmt\Rule\TrailingCommaInMultiline;
use Fmt\Rule\SingleClassElementPerStatement;
use Fmt\Rule\ModifierKeywords;
use Fmt\Rule\SingleImportPerStatement;
use Fmt\Rule\ControlStructureBraces;
use Fmt\Rule\NoLeadingImportSlash;
use Fmt\Rule\SingleTraitInsertPerStatement;
use Fmt\Rule\NewWithParentheses;
use Fmt\Rule\PhpdocAnnotationRemove;
use Fmt\Rule\PhpdocInlineTagNormalizer;
use Fmt\Rule\PhpdocTagRename;
use Fmt\Rule\PhpdocTagType;
use Fmt\Rule\PhpdocTrim;
use Fmt\Rule\PhpdocTrimConsecutiveBlankLineSeparation;
use Fmt\Rule\PhpdocSingleLineVarSpacing;
use Fmt\Rule\PhpdocVarAnnotationCorrectOrder;
use Fmt\Rule\PhpdocAnnotationWithoutDot;
use Fmt\Rule\PhpdocSummary;
use Fmt\Rule\NoEmptyPhpdoc;
use Fmt\Rule\PhpdocScalar;
use Fmt\Rule\PhpdocTypes;
use Fmt\Rule\PhpdocTypesOrder;
use Fmt\Rule\PhpdocNoUselessInheritdoc;
use Fmt\Rule\PhpdocVarWithoutName;
use Fmt\Rule\PhpUnitFqcnAnnotation;

/**
 * Rule registry: name + options (+ the whitespace config) -> GapRule. Sets, aliases and the command line are RuleSets / Aliases / Cli.
 */
final class Rules
{
    /*
     * THE REGISTRY. A rule is implemented when `make()` below knows its name: to add one, write src/Rule/<Name>.php and add an
     * `if ($name === '<rule_name>')` branch (options validated by the helpers at the bottom). `list-rules`, `list-sets`, the
     * not-implemented check and the presets all derive from it (Fixers::implementedNames asks make() for every upstream name).
     */
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
    public static function make(string $name, string $lineEnding, array $opt = [], string $indent = '    '): ?GapRule
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
            return new IndentationType($indent);
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
                self::bool($name, $opt, 'after_heredoc', false), $ap === 'standalone', $indent);
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
            return new NoBlankLinesAfterClassOpening($lineEnding);
        }
        if ($name === 'blank_line_after_opening_tag') {
            return new BlankLineAfterOpeningTag($lineEnding);
        }
        if ($name === 'blank_lines_before_namespace') {
            self::only($name, $opt, ['min_line_breaks', 'max_line_breaks']);
            $min = $opt['min_line_breaks'] ?? 2;
            $max = $opt['max_line_breaks'] ?? 2;
            if (!\is_int($min) || !\is_int($max) || $min < 0 || $max < $min) {
                throw new \InvalidArgumentException("$name: invalid min_line_breaks / max_line_breaks");
            }
            return new BlankLinesBeforeNamespace($min, $max, $lineEnding);
        }
        if ($name === 'blank_line_after_namespace') {
            return new BlankLineAfterNamespace($lineEnding);
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
                self::bool($name, $opt, 'inline_constructor_arguments', true), $lineEnding, $indent);
        }
        if ($name === 'single_line_after_imports') {
            return new SingleLineAfterImports($lineEnding);
        }
        if ($name === 'array_indentation') {
            return new ArrayIndentation($indent);
        }
        if ($name === 'no_multiple_statements_per_line') {
            return new NoMultipleStatementsPerLine($lineEnding);
        }
        if ($name === 'blank_line_between_import_groups') {
            return new BlankLineBetweenImportGroups($lineEnding);
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
            return new NoBreakComment($txt, $lineEnding);
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
        if ($name === 'control_structure_braces') {
            return new ControlStructureBraces();
        }
        if ($name === 'single_import_per_statement') {
            self::only($name, $opt, ['group_to_single_imports']);
            return new SingleImportPerStatement($lineEnding, self::bool($name, $opt, 'group_to_single_imports', true));
        }
        if ($name === 'modifier_keywords') {
            self::only($name, $opt, ['elements']);
            return new ModifierKeywords(self::subset($name, $opt, 'elements', ['const', 'method', 'property'], ['const', 'method', 'property']));
        }
        if ($name === 'single_class_element_per_statement') {
            self::only($name, $opt, ['elements']);
            return new SingleClassElementPerStatement(self::subset($name, $opt, 'elements', ['const', 'property'], ['const', 'property']));
        }
        if ($name === 'array_syntax') {
            self::only($name, $opt, ['syntax']);
            if (self::enum($name, $opt, 'syntax', ['long', 'short'], 'short') === 'long') {
                throw new \InvalidArgumentException("$name: syntax=long is not implemented");
            }
            return new ArraySyntax();
        }
        if ($name === 'trailing_comma_in_multiline') {
            self::only($name, $opt, ['after_heredoc', 'elements']);
            $el = self::subset($name, $opt, 'elements', ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'], ['arrays']);
            return new TrailingCommaInMultiline(\in_array('arrays', $el, true), \in_array('arguments', $el, true), \in_array('parameters', $el, true),
                \in_array('match', $el, true), \in_array('array_destructuring', $el, true), self::bool($name, $opt, 'after_heredoc', false));
        }
        if ($name === 'nullable_type_declaration') {
            self::only($name, $opt, ['syntax']);
            if (self::enum($name, $opt, 'syntax', ['union', 'question_mark'], 'question_mark') === 'union') {
                throw new \InvalidArgumentException("$name: syntax=union is not implemented");
            }
            return new NullableTypeDeclaration();
        }
        if ($name === 'ordered_types') {
            self::only($name, $opt, ['sort_algorithm', 'null_adjustment', 'case_sensitive']);
            return new OrderedTypes(self::enum($name, $opt, 'sort_algorithm', ['alpha', 'none'], 'alpha') === 'alpha',
                self::enum($name, $opt, 'null_adjustment', ['always_first', 'always_last', 'none'], 'always_first'), self::bool($name, $opt, 'case_sensitive', false));
        }
        if ($name === 'ordered_imports') {
            self::only($name, $opt, ['sort_algorithm', 'imports_order', 'case_sensitive']);
            $sort = self::enum($name, $opt, 'sort_algorithm', ['alpha', 'length', 'none'], 'alpha');
            if ($sort === 'length') {
                throw new \InvalidArgumentException("$name: sort_algorithm=length is not implemented");
            }
            $order = null;
            if (isset($opt['imports_order'])) {
                $order = self::strList($name, $opt['imports_order']);
                $sorted = $order;
                \sort($sorted);
                if ($sorted !== ['class', 'const', 'function']) {
                    throw new \InvalidArgumentException("$name: imports_order must list class, function and const");
                }
            }
            return new OrderedImports($lineEnding, $sort, $order, self::bool($name, $opt, 'case_sensitive', false), $indent);
        }
        if ($name === 'ordered_class_elements') {
            self::only($name, $opt, ['order', 'sort_algorithm', 'case_sensitive']);
            $sort = self::enum($name, $opt, 'sort_algorithm', ['none', 'alpha'], 'none');
            $order = isset($opt['order']) ? self::strList($name, $opt['order']) : ['use_trait', 'case', 'constant_public', 'constant_protected',
                'constant_private', 'property_public', 'property_protected', 'property_private', 'construct', 'destruct', 'magic', 'phpunit',
                'method_public', 'method_protected', 'method_private'];
            return new OrderedClassElements($order, $sort === 'alpha', self::bool($name, $opt, 'case_sensitive', false));
        }
        if ($name === 'no_unused_imports') {
            return new NoUnusedImports();
        }
        $doc = self::makeDoc($name, $lineEnding, $opt, $indent);
        if ($doc !== null) {
            return $doc;
        }
        if ($name === 'statement_indentation') {
            self::only($name, $opt, ['stick_comment_to_next_continuous_control_statement']);
            return new StatementIndentation($lineEnding, self::bool($name, $opt, 'stick_comment_to_next_continuous_control_statement', false), $indent);
        }
        return SymRules::make($name, $lineEnding, $opt, $indent);
    }

    /**
     * The phpdoc rules (docblocks are trivia: gap rules over the doc pieces of a gap, see DocRule).
     * @param array<string, mixed> $opt
     */
    private static function makeDoc(string $name, string $lineEnding, array $opt, string $indent): ?GapRule
    {
        if ($name === 'phpdoc_no_access') {
            return new PhpdocAnnotationRemove($name, ['access'], true);
        }
        if ($name === 'phpdoc_no_package') {
            return new PhpdocAnnotationRemove($name, ['package', 'subpackage'], true);
        }
        if ($name === 'general_phpdoc_annotation_remove') {
            self::only($name, $opt, ['annotations', 'case_sensitive']);
            return new PhpdocAnnotationRemove($name, isset($opt['annotations']) ? self::strList($name, $opt['annotations']) : [],
                self::bool($name, $opt, 'case_sensitive', true));
        }
        if ($name === 'general_phpdoc_tag_rename') {
            self::only($name, $opt, ['fix_annotation', 'fix_inline', 'replacements', 'case_sensitive']);
            $cs = self::bool($name, $opt, 'case_sensitive', false);
            return new PhpdocTagRename($name, self::tagReplacements($name, $opt['replacements'] ?? [], $cs), $cs,
                self::bool($name, $opt, 'fix_annotation', true), self::bool($name, $opt, 'fix_inline', true));
        }
        if ($name === 'phpdoc_no_alias_tag') {
            self::only($name, $opt, ['replacements']);
            $rep = $opt['replacements'] ?? ['property-read' => 'property', 'property-write' => 'property', 'type' => 'var', 'link' => 'see'];
            return new PhpdocTagRename($name, self::tagReplacements($name, $rep, true), true, true, false);
        }
        if ($name === 'phpdoc_inline_tag_normalizer') {
            self::only($name, $opt, ['tags']);
            return new PhpdocInlineTagNormalizer(isset($opt['tags']) ? self::strList($name, $opt['tags'])
                : ['example', 'id', 'internal', 'inheritdoc', 'inheritdocs', 'link', 'source', 'toc', 'tutorial']);
        }
        if ($name === 'phpdoc_tag_type') {
            self::only($name, $opt, ['tags']);
            $tags = $opt['tags'] ?? ['api' => 'annotation', 'author' => 'annotation', 'copyright' => 'annotation', 'deprecated' => 'annotation',
                'example' => 'annotation', 'global' => 'annotation', 'inheritDoc' => 'annotation', 'internal' => 'annotation', 'license' => 'annotation',
                'method' => 'annotation', 'package' => 'annotation', 'param' => 'annotation', 'property' => 'annotation', 'return' => 'annotation',
                'see' => 'annotation', 'since' => 'annotation', 'throws' => 'annotation', 'todo' => 'annotation', 'uses' => 'annotation',
                'var' => 'annotation', 'version' => 'annotation'];
            $norm = [];
            foreach (self::strMap($name, $tags) as $t => $type) {
                if ($type !== 'annotation' && $type !== 'inline') {
                    throw new \InvalidArgumentException("$name: unknown tag type \"$type\"");
                }
                $norm[\strtolower($t)] = $type;
            }
            return new PhpdocTagType($norm);
        }
        if ($name === 'phpdoc_trim') {
            return new PhpdocTrim();
        }
        if ($name === 'phpdoc_trim_consecutive_blank_line_separation') {
            return new PhpdocTrimConsecutiveBlankLineSeparation();
        }
        if ($name === 'phpdoc_single_line_var_spacing') {
            return new PhpdocSingleLineVarSpacing();
        }
        if ($name === 'phpdoc_var_annotation_correct_order') {
            return new PhpdocVarAnnotationCorrectOrder();
        }
        if ($name === 'phpdoc_annotation_without_dot') {
            return new PhpdocAnnotationWithoutDot();
        }
        if ($name === 'phpdoc_summary') {
            return new PhpdocSummary($lineEnding);
        }
        if ($name === 'no_empty_phpdoc') {
            return new NoEmptyPhpdoc();
        }
        if ($name === 'phpdoc_scalar') {
            self::only($name, $opt, ['types']);
            return new PhpdocScalar(self::subset($name, $opt, 'types', \array_keys(PhpdocScalar::TYPES_MAP),
                ['boolean', 'callback', 'double', 'integer', 'real', 'str']));
        }
        if ($name === 'phpdoc_types') {
            self::only($name, $opt, ['groups', 'exclude']);
            $all = \array_merge(PhpdocTypes::GROUPS['alias'], PhpdocTypes::GROUPS['meta'], PhpdocTypes::GROUPS['simple']);
            $fix = [];
            foreach (self::subset($name, $opt, 'groups', ['alias', 'meta', 'simple'], ['alias', 'meta', 'simple']) as $g) {
                foreach (PhpdocTypes::GROUPS[$g] as $t) {
                    $fix[$t] = true;
                }
            }
            foreach (self::subset($name, $opt, 'exclude', $all, []) as $t) {
                unset($fix[$t]);
            }
            return new PhpdocTypes($fix);
        }
        if ($name === 'phpdoc_no_useless_inheritdoc') {
            return new PhpdocNoUselessInheritdoc();
        }
        if ($name === 'phpdoc_var_without_name') {
            return new PhpdocVarWithoutName();
        }
        if ($name === 'php_unit_fqcn_annotation') {
            return new PhpUnitFqcnAnnotation();
        }
        if ($name === 'phpdoc_types_order') {
            self::only($name, $opt, ['sort_algorithm', 'null_adjustment', 'case_sensitive']);
            return new PhpdocTypesOrder(self::enum($name, $opt, 'sort_algorithm', ['alpha', 'none'], 'alpha') === 'alpha',
                self::enum($name, $opt, 'null_adjustment', ['always_first', 'always_last', 'none'], 'always_first'), self::bool($name, $opt, 'case_sensitive', false));
        }
        return null;
    }

    /**
     * GeneralPhpdocTagRenameFixer's normalizer of `replacements`.
     * @return array<string, string>
     */
    private static function tagReplacements(string $name, mixed $v, bool $caseSensitive): array
    {
        $out = [];
        foreach (self::strMap($name, $v) as $from => $to) {
            if (\preg_match('#^\S+$#', $to) !== 1 || \str_contains($to, '*/')) {
                throw new \InvalidArgumentException("$name: tag \"$from\" cannot be replaced by invalid tag \"$to\"");
            }
            $from = \trim($from);
            $to = \trim($to);
            if (!$caseSensitive) {
                $lower = \strtolower($from);
                if (isset($out[$lower]) && $out[$lower] !== $to) {
                    throw new \InvalidArgumentException("$name: tag \"$from\" cannot be configured to be replaced with several different tags when case sensitivity is off");
                }
                $from = $lower;
            }
            $out[$from] = $to;
        }
        foreach ($out as $from => $to) {
            if (isset($out[$to]) && $out[$to] !== $to) {
                throw new \InvalidArgumentException("$name: cannot change tag \"$from\" to tag \"$to\", as the tag \"$to\" is configured to be replaced to \"{$out[$to]}\"");
            }
        }
        return $out;
    }

    /** @return array<string, string> */
    private static function strMap(string $name, mixed $v): array
    {
        if (!\is_array($v)) {
            throw new \InvalidArgumentException("$name: expected a map of strings");
        }
        $out = [];
        foreach ($v as $k => $x) {
            if (!\is_string($x)) {
                throw new \InvalidArgumentException("$name: expected a map of strings");
            }
            $out[(string) $k] = $x;
        }
        return $out;
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
    public static function subset(string $name, array $opt, string $key, array $allowed, array $default): array
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
    public static function only(string $name, array $opt, array $keys): void
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
    public static function enum(string $name, array $opt, string $key, array $allowed, string $default): string
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
    public static function bool(string $name, array $opt, string $key, bool $default): bool
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
    public static function strList(string $name, mixed $v): array
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
}
