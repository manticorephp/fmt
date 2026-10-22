<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Upstream's deprecated fixer names (proxies for a successor) and the v2 names that upstream 3 no longer accepts. A name maps to
 * its successor; fixed options replace whatever the entry carried when it was `true` (a proxy's own configuration), an options
 * array is forwarded as it is. `single_space_after_construct` has its own option translation.
 */
final class Aliases
{
    /**
     * name => [successor, fixed options (JSON) or '' to forward].
     * Deprecated fixers with one successor and plain forwarding are derived from the fixer table (see target()).
     */
    private const FIXED = [
        'no_blank_lines_before_namespace' => ['blank_lines_before_namespace', '{"min_line_breaks":0,"max_line_breaks":1}'],
        'single_blank_line_before_namespace' => ['blank_lines_before_namespace', '{"min_line_breaks":2,"max_line_breaks":2}'],
        'blank_line_before_return' => ['blank_line_before_statement', '{"statements":["return"]}'],
        'hash_to_slash_comment' => ['single_line_comment_style', '{"comment_types":["hash"]}'],
        'lowercase_constants' => ['constant_case', '{"case":"lower"}'],
        'no_short_echo_tag' => ['echo_tag_syntax', '{"format":"long"}'],
        'php_unit_ordered_covers' => ['phpdoc_order_by_value', '{"annotations":["covers"]}'],
        'pre_increment' => ['increment_style', '{"style":"pre"}'],
        'trailing_comma_in_multiline_array' => ['trailing_comma_in_multiline', '{"elements":["arrays"]}'],
        'function_typehint_space' => ['type_declaration_spaces', '{"elements":["function"]}'],
        'no_trailing_comma_in_list_call' => ['no_trailing_comma_in_singleline', '{"elements":["array_destructuring"]}'],
        'no_trailing_comma_in_singleline_array' => ['no_trailing_comma_in_singleline', '{"elements":["array"]}'],
        'no_trailing_comma_in_singleline_function_call' => ['no_trailing_comma_in_singleline', '{"elements":["arguments","array_destructuring"]}'],
    ];

    /** v2 names upstream 3 rejects ("is renamed"), forwarded as they are */
    private const RENAMED = [
        'final_static_access' => 'self_static_accessor',
        'no_extra_consecutive_blank_lines' => 'no_extra_blank_lines',
        'no_multiline_whitespace_before_semicolons' => 'multiline_whitespace_before_semicolons',
        'silenced_deprecation_error' => 'error_suppression',
        'psr4' => 'psr_autoloading',
    ];

    /**
     * The rule an entry stands for: [name, value] with value true or an options array; null when the name is no alias.
     * @return array{0: string, 1: mixed}|null
     */
    public static function target(string $name, mixed $value): ?array
    {
        if ($name === 'single_space_after_construct') {
            $cons = $value;
            if (!\is_array($value) || !isset($value['constructs'])) {
                $cons = null;
                foreach (Fixers::all()[$name]['opts'] as $o) {
                    /** @var array<string, mixed> $o */
                    if ($o['name'] === 'constructs') {
                        $cons = $o['def'];
                    }
                }
            } else {
                $cons = $value['constructs'];
            }
            Notices::add('Rule "' . $name . '" is deprecated. Use "single_space_around_construct" instead.');
            return ['single_space_around_construct', ['constructs_contain_a_single_space' => ['yield_from'],
                'constructs_preceded_by_a_single_space' => [], 'constructs_followed_by_a_single_space' => $cons]];
        }
        if ($name === 'escape_implicit_backslashes') {
            // EscapeImplicitBackslashesFixer::configurePostNormalisation: three bool options become the successor's escape / ignore
            $o = \is_array($value) ? $value : [];
            $map = ['single_quoted' => ['single_quoted', false], 'double_quoted' => ['double_quoted', true], 'heredoc_syntax' => ['heredoc', true]];
            $out = [];
            foreach ($map as $old => [$new, $def]) {
                $on = \array_key_exists($old, $o) ? $o[$old] : $def;
                $out[$new] = $on === true ? 'escape' : 'ignore';
                unset($o[$old]);
            }
            foreach ($o as $k => $v) {
                $out[(string) $k] = $v; // unknown option: the successor reports it
            }
            Notices::add('Rule "' . $name . '" is deprecated. Use "string_implicit_backslashes" instead.');
            return ['string_implicit_backslashes', $out];
        }
        if (isset(self::FIXED[$name])) {
            [$to, $json] = self::FIXED[$name];
            Notices::add('Rule "' . $name . '" is deprecated. Use "' . $to . '" instead.');
            if ($value === true || ($value === [] && $json !== '')) {
                return [$to, \json_decode($json, true)];
            }
            return [$to, $value];
        }
        if (isset(self::RENAMED[$name])) {
            Notices::add('Rule "' . $name . '" is renamed. Use "' . self::RENAMED[$name] . '" instead.');
            return [self::RENAMED[$name], $value];
        }
        $succ = Fixers::successors($name);
        if ($succ !== null && \count($succ) === 1) {
            Notices::add('Rule "' . $name . '" is deprecated. Use "' . $succ[0] . '" instead.');
            return [$succ[0], $value];
        }
        if ($succ !== null) {
            Notices::add('Rule "' . $name . '" is deprecated' . ($succ === [] ? ' and will be removed in version 4.0.' : '. Use ' . RuleSets::joinNames($succ) . ' instead.'));
        }
        return null;
    }

    /**
     * The rules an entry stands for, in the order upstream's proxy runs them: [name, value] pairs. Like target(), but a proxy of several
     * fixers (`braces`) expands to all of them; null when the name is no alias.
     * @return list<array{0: string, 1: mixed}>|null
     */
    public static function expand(string $name, mixed $value): ?array
    {
        if ($name === 'braces') {
            self::target($name, $value); // the deprecation notice
            $o = [];
            if (\is_array($value)) {
                foreach ($value as $k => $v) {
                    $o[(string) $k] = $v;
                }
            }
            Rules::only($name, $o, ['allow_single_line_anonymous_class_with_empty_body', 'allow_single_line_closure', 'position_after_functions_and_oop_constructs',
                'position_after_control_structures', 'position_after_anonymous_constructs']);
            $ctl = Rules::enum($name, $o, 'position_after_control_structures', ['next', 'same'], 'same');
            $fn = Rules::enum($name, $o, 'position_after_functions_and_oop_constructs', ['next', 'same'], 'next');
            $anon = Rules::enum($name, $o, 'position_after_anonymous_constructs', ['next', 'same'], 'same');
            $pos = static fn (string $v): string => $v === 'next' ? 'next_line_unless_newline_at_signature_end' : 'same_line';
            return [
                ['single_space_around_construct', ['constructs_contain_a_single_space' => [], 'constructs_followed_by_a_single_space' =>
                    ['elseif', 'for', 'foreach', 'if', 'match', 'while', 'use_lambda'], 'constructs_preceded_by_a_single_space' => ['use_lambda']]],
                ['control_structure_braces', true],
                ['control_structure_continuation_position', ['position' => $ctl === 'next' ? 'next_line' : 'same_line']],
                ['declare_parentheses', true],
                ['no_multiple_statements_per_line', true],
                ['braces_position', ['control_structures_opening_brace' => $pos($ctl), 'functions_opening_brace' => $pos($fn),
                    'anonymous_functions_opening_brace' => $pos($anon), 'classes_opening_brace' => $pos($fn), 'anonymous_classes_opening_brace' => $pos($anon),
                    'allow_single_line_empty_anonymous_classes' => Rules::bool($name, $o, 'allow_single_line_anonymous_class_with_empty_body', false),
                    'allow_single_line_anonymous_functions' => Rules::bool($name, $o, 'allow_single_line_closure', false)]],
                ['statement_indentation', ['braces_fixer_compatibility' => true]],
                ['no_extra_blank_lines', ['tokens' => ['curly_brace_block']]],
            ];
        }
        $t = self::target($name, $value);
        return $t === null ? null : [$t];
    }

    /** A deprecated fixer upstream runs through its successors, all of which are implemented here. */
    public static function isProxy(string $name): bool
    {
        if ($name === 'braces') {
            return true; // the eight fixers of Aliases::expand
        }
        if ($name === 'single_space_after_construct' || $name === 'escape_implicit_backslashes' || isset(self::FIXED[$name])) {
            $to = $name === 'single_space_after_construct' ? 'single_space_around_construct'
                : ($name === 'escape_implicit_backslashes' ? 'string_implicit_backslashes' : self::FIXED[$name][0]);
            return Rules::make($to, "\n") !== null;
        }
        $succ = Fixers::successors($name);
        return $succ !== null && \count($succ) === 1 && Rules::make($succ[0], "\n") !== null;
    }

    /** The priority the alias (a deprecated proxy fixer) has upstream, null when the name is no upstream fixer. */
    public static function slot(string $name): ?int
    {
        $f = Fixers::all()[$name] ?? null;
        return $f === null ? null : (int) $f['prio'];
    }

    /** Whether the name is accepted: a fixer upstream knows or a v2 name. */
    public static function known(string $name): bool
    {
        return Fixers::exists($name) || isset(self::RENAMED[$name]) || isset(self::FIXED[$name]);
    }

    /** upstream's WordMatcher: the closest known rule name, or null */
    public static function suggest(string $needle): ?string
    {
        $best = null;
        $dist = \ceil(\strlen($needle) * 0.35);
        foreach (\array_keys(Fixers::all()) as $cand) {
            $d = \levenshtein($needle, (string) $cand);
            if ($d < $dist) {
                $best = (string) $cand;
                $dist = $d;
            }
        }
        return $best;
    }
}
