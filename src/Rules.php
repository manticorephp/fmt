<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\IndentationType;
use Fmt\Rule\LineEnding;
use Fmt\Rule\NoTrailingWhitespace;
use Fmt\Rule\NoWhitespaceInBlankLine;
use Fmt\Rule\SingleBlankLineAtEof;

/**
 * Rule registry + presets. `--rules` is cs-fixer's: a csv of names/presets, or a JSON object
 * (`{"@PSR12": true, "no_extra_blank_lines": {"tokens": ["use"]}, "x": false}`).
 */
final class Rules
{
    public const PSR12 = 'binary_operator_spaces blank_line_after_opening_tag blank_line_between_import_groups blank_lines_before_namespace braces_position class_definition compact_nullable_type_declaration declare_equal_normalize lowercase_cast lowercase_static_reference modifier_keywords new_with_parentheses no_blank_lines_after_class_opening no_extra_blank_lines no_leading_import_slash no_whitespace_in_blank_line ordered_class_elements ordered_imports return_type_declaration short_scalar_cast single_import_per_statement single_space_around_construct single_trait_insert_per_statement ternary_operator_spaces unary_operator_spaces blank_line_after_namespace constant_case control_structure_braces control_structure_continuation_position elseif function_declaration indentation_type line_ending lowercase_keywords method_argument_space no_break_comment no_closing_tag no_multiple_statements_per_line no_space_around_double_colon no_spaces_after_function_name no_trailing_whitespace no_trailing_whitespace_in_comment single_blank_line_at_eof single_class_element_per_statement single_line_after_imports spaces_inside_parentheses statement_indentation switch_case_semicolon_to_colon switch_case_space encoding full_opening_tag';

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
        return null;
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
        if ($rule === 'no_extra_blank_lines') {
            return ['tokens' => ['use']];
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
