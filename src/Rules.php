<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\LineEnding;
use Fmt\Rule\NoTrailingWhitespace;
use Fmt\Rule\NoWhitespaceInBlankLine;

/** Rule registry + presets. Presets are name lists only (options arrive with the YAML/JSON config). */
final class Rules
{
    public const PSR12 = 'binary_operator_spaces blank_line_after_opening_tag blank_line_between_import_groups blank_lines_before_namespace braces_position class_definition compact_nullable_type_declaration declare_equal_normalize lowercase_cast lowercase_static_reference modifier_keywords new_with_parentheses no_blank_lines_after_class_opening no_extra_blank_lines no_leading_import_slash no_whitespace_in_blank_line ordered_class_elements ordered_imports return_type_declaration short_scalar_cast single_import_per_statement single_space_around_construct single_trait_insert_per_statement ternary_operator_spaces unary_operator_spaces blank_line_after_namespace constant_case control_structure_braces control_structure_continuation_position elseif function_declaration indentation_type line_ending lowercase_keywords method_argument_space no_break_comment no_closing_tag no_multiple_statements_per_line no_space_around_double_colon no_spaces_after_function_name no_trailing_whitespace no_trailing_whitespace_in_comment single_blank_line_at_eof single_class_element_per_statement single_line_after_imports spaces_inside_parentheses statement_indentation switch_case_semicolon_to_colon switch_case_space encoding full_opening_tag';

    public static function make(string $name, string $lineEnding): ?GapRule
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
        return null;
    }

    /**
     * @return string[] rule names in the given order (presets expanded in place, duplicates dropped)
     */
    public static function names(string $csv): array
    {
        $names = [];
        foreach (\explode(',', $csv) as $item) {
            $item = \trim($item);
            if ($item === '') {
                continue;
            }
            $list = $item === '@PSR12' ? \explode(' ', self::PSR12) : [$item];
            foreach ($list as $nm) {
                if (!\in_array($nm, $names, true)) {
                    $names[] = $nm;
                }
            }
        }
        return $names;
    }
}
