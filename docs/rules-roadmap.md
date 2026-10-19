# Rules roadmap

Computed from upstream 3.95.27 `RuleSet` (inclusions resolved: `@PER-CS3x0` > `@PER-CS2x0` > `@PER-CS1x0` > `@PSR12` > `@PSR2` > `@PSR1`).

| Set | rules | delta |
|---|---|---|
| @PSR12 | 51 | base (8 done, 43 todo) |
| @PER-CS2x0 | 58 | +7 new rules vs @PSR12, 4 option changes, 0 dropped |
| @PER-CS3x0 | 62 | +4 new rules vs CS2x0 (+11 vs @PSR12), 1 option change |
| @Symfony | 151 | +89 vs CS3x0 (superset; later) |

**Beyond @PSR12: PER-CS2.0 needs 7 more rules, PER-CS3.0 needs 11** (array_indentation, array_syntax, attribute_block_no_spaces,
cast_spaces, concat_space, single_line_empty_body, trailing_comma_in_multiline; then nullable_type_declaration, operator_linebreak,
ordered_types, types_spaces), plus option sets on rules PSR12 already needs: function_declaration `closure_fn_spacing=none`,
method_argument_space `{after_heredoc:false}` (no attribute_placement / on_multiline), new_with_parentheses `anonymous_class=false`,
single_space_around_construct (longer construct lists: const, enum, finally, match, named_argument, readonly, type_colon, use, ...),
single_class_element_per_statement `true` (all elements).

Kind: **gap** = rewrites trivia only, **text** = rewrites one token's text (`fixText`), **edit** = inserts/removes/moves tokens
(needs the rebuild phase, not built), **struct** = gap/text but needs the Structure table. Fork cost: not recorded for most rules;
measured ones are in the status column.

## @PSR12 (51)

| rule | status | kind |
|---|---|---|
| binary_operator_spaces | done (single_space, at_least_single_space, no_space; align* refused) | gap, struct |
| blank_line_after_opening_tag | todo | gap |
| blank_line_between_import_groups | todo | gap, struct |
| blank_lines_before_namespace | todo | gap |
| braces_position | done | gap, struct |
| class_definition | todo | gap, struct |
| compact_nullable_type_declaration | todo | gap |
| declare_equal_normalize | todo | gap |
| lowercase_cast | todo | text |
| lowercase_static_reference | todo | text |
| modifier_keywords | todo | edit (reorder) |
| new_with_parentheses | todo | edit (insert `()`) |
| no_blank_lines_after_class_opening | todo | gap |
| no_extra_blank_lines | done | gap, struct |
| no_leading_import_slash | todo | edit (delete) |
| no_whitespace_in_blank_line | done | gap |
| ordered_class_elements | todo | edit (move) |
| ordered_imports | todo | edit (move) |
| return_type_declaration | todo | gap |
| short_scalar_cast | todo | text |
| single_import_per_statement | todo | edit (insert) |
| single_space_around_construct | wave 2 | gap, struct |
| single_trait_insert_per_statement | todo | edit |
| ternary_operator_spaces | wave 2 | gap, struct |
| unary_operator_spaces | wave 2 | gap |
| blank_line_after_namespace | todo | gap |
| constant_case | todo | text |
| control_structure_braces | todo | edit (insert braces) |
| control_structure_continuation_position | todo | gap |
| elseif | todo | edit (token replace) |
| function_declaration | todo | gap |
| indentation_type | done | gap |
| line_ending | done | gap |
| lowercase_keywords | todo | text |
| method_argument_space | done | gap, struct |
| no_break_comment | todo | edit (comment insert) |
| no_closing_tag | todo | edit (delete) |
| no_multiple_statements_per_line | todo | gap, struct |
| no_space_around_double_colon | todo | gap |
| no_spaces_after_function_name | todo | gap |
| no_trailing_whitespace | done | gap |
| no_trailing_whitespace_in_comment | todo | gap (comments are trivia) |
| single_blank_line_at_eof | done | gap |
| single_class_element_per_statement | todo | edit (split) |
| single_line_after_imports | todo | gap, struct |
| spaces_inside_parentheses | todo | gap |
| statement_indentation | done | gap, struct |
| switch_case_semicolon_to_colon | todo | text |
| switch_case_space | todo | gap |
| encoding | todo | whole file (BOM) |
| full_opening_tag | todo | text |

## Added by @PER-CS2x0 / @PER-CS3x0

| rule | set | status | kind |
|---|---|---|---|
| cast_spaces | CS2 | wave 2 | gap |
| concat_space | CS2 | wave 2 | gap, struct |
| array_indentation | CS2 | todo | gap, struct |
| array_syntax | CS2 | todo | edit (`array(` to `[`) |
| attribute_block_no_spaces | CS2 | todo | gap |
| single_line_empty_body | CS2 | todo | gap |
| trailing_comma_in_multiline | CS2 | todo | edit (insert `,`) |
| nullable_type_declaration | CS3 | todo | edit (`?T` / `T\|null`) |
| operator_linebreak | CS3 | todo | edit (move operator) |
| ordered_types | CS3 | todo | edit (reorder) |
| types_spaces | CS3 | todo | gap |

"wave 2" = ported in this wave (see git log). @Symfony adds 89 rules (mostly phpdoc_*, edit rules); not planned yet.
