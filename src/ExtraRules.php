<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\AttributeEmptyParentheses;
use Fmt\Rule\ClassKeywordRemove;
use Fmt\Rule\DoctrineAnnotationArrayAssignment;
use Fmt\Rule\DoctrineAnnotationBraces;
use Fmt\Rule\DoctrineAnnotationIndentation;
use Fmt\Rule\DoctrineAnnotationSpaces;
use Fmt\Rule\GeneralAttributeRemove;
use Fmt\Rule\GroupImport;
use Fmt\Rule\HeaderComment;
use Fmt\Rule\HeredocClosingMarker;
use Fmt\Rule\HeredocIndentation;
use Fmt\Rule\ListSyntax;
use Fmt\Rule\LongToShorthandOperator;
use Fmt\Rule\MultilinePromotedProperties;
use Fmt\Rule\MultilineStringToHeredoc;
use Fmt\Rule\NewExpressionParentheses;
use Fmt\Rule\NoRedundantReadonlyProperty;
use Fmt\Rule\NotOperatorWithSpace;
use Fmt\Rule\NumericLiteralSeparator;
use Fmt\Rule\OctalNotation;
use Fmt\Rule\OrderedAttributes;
use Fmt\Rule\OrderedInterfaces;
use Fmt\Rule\PhpUnitAttributes;
use Fmt\Rule\PhpUnitSizeClass;
use Fmt\Rule\PhpdocLineSpan;
use Fmt\Rule\PhpdocParamOrder;
use Fmt\Rule\PhpdocTagNoNamedArguments;
use Fmt\Rule\ReturnToYieldFrom;
use Fmt\Rule\SimplifiedIfReturn;
use Fmt\Rule\SimplifiedNullReturn;
use Fmt\Rule\StringableForToString;
use Fmt\Rule\TernaryToNullCoalescing;

/**
 * The last link of the registry chain (Rules -> SymRules -> RiskyRules -> here): the rules added to finish the upstream list. Same contract as Rules::make.
 */
final class ExtraRules
{
    /**
     * @param array<string, mixed> $opt
     * @throws \InvalidArgumentException on an unknown or invalid option
     */
    public static function make(string $name, string $lineEnding, array $opt = [], string $indent = '    '): ?GapRule
    {
        if ($name === 'octal_notation') {
            return new OctalNotation();
        }
        if ($name === 'not_operator_with_space') {
            return new NotOperatorWithSpace(false);
        }
        if ($name === 'not_operator_with_successor_space') {
            return new NotOperatorWithSpace(true);
        }
        if ($name === 'list_syntax') {
            Rules::only($name, $opt, ['syntax']);
            return new ListSyntax(Rules::enum($name, $opt, 'syntax', ['long', 'short'], 'short') === 'short');
        }
        if ($name === 'attribute_empty_parentheses') {
            Rules::only($name, $opt, ['use_parentheses']);
            return new AttributeEmptyParentheses(Rules::bool($name, $opt, 'use_parentheses', false));
        }
        if ($name === 'new_expression_parentheses') {
            Rules::only($name, $opt, ['use_parentheses']);
            return new NewExpressionParentheses(Rules::bool($name, $opt, 'use_parentheses', false));
        }
        if ($name === 'assign_null_coalescing_to_coalesce_equal') {
            return new LongToShorthandOperator(true);
        }
        if ($name === 'simplified_null_return') {
            return new SimplifiedNullReturn();
        }
        if ($name === 'ternary_to_null_coalescing') {
            return new TernaryToNullCoalescing();
        }
        if ($name === 'stringable_for_to_string') {
            return new StringableForToString();
        }
        if ($name === 'simplified_if_return') {
            return new SimplifiedIfReturn();
        }
        if ($name === 'return_to_yield_from') {
            return new ReturnToYieldFrom();
        }
        if ($name === 'no_redundant_readonly_property') {
            return new NoRedundantReadonlyProperty();
        }
        if ($name === 'class_keyword_remove') {
            return new ClassKeywordRemove();
        }
        if ($name === 'numeric_literal_separator') {
            Rules::only($name, $opt, ['override_existing', 'strategy']);
            return new NumericLiteralSeparator(
                Rules::bool($name, $opt, 'override_existing', false),
                Rules::enum($name, $opt, 'strategy', ['use_separator', 'no_separator'], 'use_separator') === 'use_separator',
            );
        }
        if ($name === 'heredoc_indentation') {
            Rules::only($name, $opt, ['indentation']);
            return new HeredocIndentation(Rules::enum($name, $opt, 'indentation', ['start_plus_one', 'same_as_start'], 'start_plus_one') === 'start_plus_one', $indent);
        }
        if ($name === 'heredoc_closing_marker') {
            Rules::only($name, $opt, ['closing_marker', 'reserved_closing_markers', 'explicit_heredoc_style']);
            $marker = $opt['closing_marker'] ?? 'EOD';
            if (!\is_string($marker)) {
                throw new \InvalidArgumentException("$name: closing_marker must be a string");
            }
            return new HeredocClosingMarker($marker, isset($opt['reserved_closing_markers']) ? Rules::strList($name, $opt['reserved_closing_markers'])
                : ['CSS', 'DIFF', 'HTML', 'JS', 'JSON', 'MD', 'PHP', 'PYTHON', 'RST', 'TS', 'SQL', 'XML', 'YAML'], Rules::bool($name, $opt, 'explicit_heredoc_style', false));
        }
        if ($name === 'multiline_string_to_heredoc') {
            return new MultilineStringToHeredoc();
        }
        if ($name === 'general_attribute_remove') {
            Rules::only($name, $opt, ['attributes']);
            return new GeneralAttributeRemove(isset($opt['attributes']) ? Rules::strList($name, $opt['attributes']) : []);
        }
        if ($name === 'ordered_attributes') {
            Rules::only($name, $opt, ['sort_algorithm', 'order']);
            $order = isset($opt['order']) ? Rules::strList($name, $opt['order']) : [];
            if ($order !== \array_values(\array_unique($order))) {
                throw new \InvalidArgumentException("$name: The list includes attributes that are not unique.");
            }
            $custom = Rules::enum($name, $opt, 'sort_algorithm', ['alpha', 'custom'], 'alpha') === 'custom';
            if ($custom && $order === []) {
                throw new \InvalidArgumentException("$name: The custom order strategy requires providing `order` option with a list of attributes's FQNs.");
            }
            $flat = [];
            foreach ($order as $a) {
                $flat[] = \ltrim($a, '\\');
            }
            return new OrderedAttributes($custom, $flat);
        }
        if ($name === 'ordered_interfaces') {
            Rules::only($name, $opt, ['order', 'direction', 'case_sensitive']);
            return new OrderedInterfaces(
                Rules::enum($name, $opt, 'order', ['alpha', 'length'], 'alpha') === 'length',
                Rules::enum($name, $opt, 'direction', ['ascend', 'descend'], 'ascend') === 'descend',
                Rules::bool($name, $opt, 'case_sensitive', false),
            );
        }
        if ($name === 'multiline_promoted_properties') {
            Rules::only($name, $opt, ['keep_blank_lines', 'minimum_number_of_parameters']);
            $min = $opt['minimum_number_of_parameters'] ?? 1;
            if (!\is_int($min)) {
                throw new \InvalidArgumentException("$name: minimum_number_of_parameters must be an int");
            }
            return new MultilinePromotedProperties(Rules::bool($name, $opt, 'keep_blank_lines', false), $min, $lineEnding, $indent);
        }
        if ($name === 'group_import') {
            Rules::only($name, $opt, ['group_types']);
            $types = Rules::subset($name, $opt, 'group_types', ['classy', 'functions', 'constants'], ['classy', 'functions', 'constants']);
            return new GroupImport($types);
        }
        if ($name === 'php_unit_size_class') {
            Rules::only($name, $opt, ['group']);
            return new PhpUnitSizeClass(Rules::enum($name, $opt, 'group', ['small', 'medium', 'large'], 'small'), $lineEnding);
        }
        if ($name === 'php_unit_attributes') {
            Rules::only($name, $opt, ['keep_annotations']);
            return new PhpUnitAttributes(Rules::bool($name, $opt, 'keep_annotations', false));
        }
        if ($name === 'phpdoc_tag_no_named_arguments') {
            Rules::only($name, $opt, ['description', 'fix_attribute', 'fix_internal']);
            $desc = $opt['description'] ?? '';
            if (!\is_string($desc)) {
                throw new \InvalidArgumentException("$name: description must be a string");
            }
            return new PhpdocTagNoNamedArguments($desc, Rules::bool($name, $opt, 'fix_attribute', true), Rules::bool($name, $opt, 'fix_internal', true), $lineEnding);
        }
        if ($name === 'phpdoc_param_order') {
            return new PhpdocParamOrder();
        }
        if ($name === 'phpdoc_line_span') {
            $kinds = ['class' => 'multi', 'trait_import' => '', 'const' => 'multi', 'property' => 'multi', 'method' => 'multi', 'case' => 'multi', 'function' => 'multi', 'other' => ''];
            Rules::only($name, $opt, \array_keys($kinds));
            $cfg = [];
            foreach ($kinds as $kind => $def) {
                $v = \array_key_exists($kind, $opt) ? $opt[$kind] : $def;
                if ($v === null) {
                    $v = '';
                }
                if ($v !== '' && $v !== 'single' && $v !== 'multi') {
                    throw new \InvalidArgumentException("$name: invalid value for $kind");
                }
                $cfg[$kind] = (string) $v;
            }
            return new PhpdocLineSpan($cfg, $lineEnding);
        }
        if ($name === 'doctrine_annotation_array_assignment' || $name === 'doctrine_annotation_braces' || $name === 'doctrine_annotation_indentation'
            || $name === 'doctrine_annotation_spaces') {
            $own = match ($name) {
                'doctrine_annotation_array_assignment' => ['operator'],
                'doctrine_annotation_braces' => ['syntax'],
                'doctrine_annotation_indentation' => ['indent_mixed_lines'],
                default => ['around_parentheses', 'around_commas', 'before_argument_assignments', 'after_argument_assignments', 'before_array_assignments_equals',
                    'after_array_assignments_equals', 'before_array_assignments_colon', 'after_array_assignments_colon'],
            };
            Rules::only($name, $opt, \array_merge(['ignored_tags'], $own));
            $ignored = isset($opt['ignored_tags']) ? Rules::strList($name, $opt['ignored_tags']) : DoctrineAnnotationRule::IGNORED;
            if ($name === 'doctrine_annotation_array_assignment') {
                return new DoctrineAnnotationArrayAssignment($ignored, Rules::enum($name, $opt, 'operator', ['=', ':'], '='));
            }
            if ($name === 'doctrine_annotation_braces') {
                return new DoctrineAnnotationBraces($ignored, Rules::enum($name, $opt, 'syntax', ['with_braces', 'without_braces'], 'without_braces') === 'with_braces');
            }
            if ($name === 'doctrine_annotation_indentation') {
                return new DoctrineAnnotationIndentation($ignored, Rules::bool($name, $opt, 'indent_mixed_lines', false));
            }
            $assign = [];
            foreach (['before_argument_assignments' => 0, 'after_argument_assignments' => 0, 'before_array_assignments_equals' => 1, 'after_array_assignments_equals' => 1,
                'before_array_assignments_colon' => 1, 'after_array_assignments_colon' => 1] as $key => $def) {
                $v = \array_key_exists($key, $opt) ? $opt[$key] : ($def === 1);
                if ($v !== null && !\is_bool($v)) {
                    throw new \InvalidArgumentException("$name: $key must be a bool or null");
                }
                $assign[$key] = $v === null ? -1 : ($v ? 1 : 0);
            }
            return new DoctrineAnnotationSpaces($ignored, Rules::bool($name, $opt, 'around_parentheses', true), Rules::bool($name, $opt, 'around_commas', true), $assign);
        }
        if ($name === 'header_comment') {
            Rules::only($name, $opt, ['header', 'validator', 'comment_type', 'location', 'separate']);
            $header = $opt['header'] ?? null;
            if ($header !== null) {
                if (!\is_string($header)) {
                    throw new \InvalidArgumentException("$name: header must be a string");
                }
                if (\trim($header) === '') {
                    $header = '';
                } elseif (\str_contains($header, '*/')) {
                    throw new \InvalidArgumentException("$name: Cannot use '*/' in header.");
                }
            }
            $validator = $opt['validator'] ?? null;
            if ($validator !== null) {
                if (!\is_string($validator)) {
                    throw new \InvalidArgumentException("$name: validator must be a string or null");
                }
                if (\preg_match($validator . 'u', '') === false) {
                    throw new \InvalidArgumentException("$name: Provided RegEx is not valid.");
                }
            }
            return new HeaderComment(
                $header,
                $validator,
                Rules::enum($name, $opt, 'comment_type', ['PHPDoc', 'comment'], 'comment') === 'PHPDoc',
                Rules::enum($name, $opt, 'location', ['after_open', 'after_declare_strict'], 'after_declare_strict') === 'after_open',
                Rules::enum($name, $opt, 'separate', ['both', 'top', 'bottom', 'none'], 'both'),
                $lineEnding,
            );
        }
        return null;
    }
}
