# Rules roadmap

Computed from upstream 3.95.27 `RuleSet` (inclusions resolved: `@PER-CS3x0` > `@PER-CS2x0` > `@PER-CS1x0` > `@PSR12` > `@PSR2` > `@PSR1`).

| Set | rules | delta |
|---|---|---|
| @PSR12 | 51 | base (51 done) |
| @PER-CS2x0 | 58 | +7 new rules vs @PSR12, 4 option changes, 0 dropped; 58 done |
| @PER-CS3x0 | 62 | +4 new rules vs CS2x0 (+11 vs @PSR12), 1 option change; 62 done |
| @Symfony | 151 | +89 vs CS3x0 (superset; later) |

**Beyond @PSR12: PER-CS2.0 needs 7 more rules, PER-CS3.0 needs 11** (array_indentation, array_syntax, attribute_block_no_spaces,
cast_spaces, concat_space, single_line_empty_body, trailing_comma_in_multiline; then nullable_type_declaration, operator_linebreak,
ordered_types, types_spaces), plus option sets on rules PSR12 already needs: function_declaration `closure_fn_spacing=none`,
method_argument_space `{after_heredoc:false}` (no attribute_placement / on_multiline), new_with_parentheses `anonymous_class=false`,
single_space_around_construct (longer construct lists: const, enum, finally, match, named_argument, readonly, type_colon, use, ...),
single_class_element_per_statement `true` (all elements).

Kind: **gap** = rewrites trivia only, **text** = rewrites one token's text (`fixText`), **edit** = inserts/removes/moves tokens
(the edit phase: `Source::edit` + re-parse), **struct** = gap/text but needs the Structure table. Fork cost: not recorded for most rules;
measured ones are in the status column.

## @PSR12 (51)

| rule | status | kind |
|---|---|---|
| binary_operator_spaces | done (single_space, at_least_single_space, no_space; align* refused) | gap, struct |
| blank_line_after_opening_tag | done (file rule) | gap |
| blank_line_between_import_groups | done | gap, struct |
| blank_lines_before_namespace | done (min/max_line_breaks) | gap |
| braces_position | done | gap, struct |
| class_definition | done (every option) | gap, struct |
| compact_nullable_type_declaration | done | gap |
| declare_equal_normalize | done (space=none; single refused) | gap |
| lowercase_cast | done | text |
| lowercase_static_reference | done | text |
| modifier_keywords | done (elements) | edit (reorder) |
| new_with_parentheses | done (named_class, anonymous_class) | edit (insert `()`) |
| no_blank_lines_after_class_opening | done | gap |
| no_extra_blank_lines | done | gap, struct |
| no_leading_import_slash | done | edit (delete) |
| no_whitespace_in_blank_line | done | gap |
| ordered_class_elements | done (order, sort_algorithm none / alpha, case_sensitive) | edit (move) |
| ordered_imports | done (sort_algorithm none / alpha, imports_order, case_sensitive; length refused) | edit (move) |
| return_type_declaration | done (space_before none, one) | gap |
| short_scalar_cast | done | text |
| single_import_per_statement | done (group_to_single_imports) | edit (insert) |
| single_space_around_construct | done | gap, struct |
| single_trait_insert_per_statement | done | edit |
| ternary_operator_spaces | done | gap, struct |
| unary_operator_spaces | done | gap |
| blank_line_after_namespace | done | gap |
| constant_case | done (lower, upper) | text |
| control_structure_braces | done | edit (insert braces) |
| control_structure_continuation_position | done (same_line; next_line refused) | gap |
| elseif | done | edit (token replace) |
| function_declaration | done (all options; a deleted trailing comma is reported) | gap |
| indentation_type | done | gap |
| line_ending | done | gap |
| lowercase_keywords | done | text |
| method_argument_space | done | gap, struct |
| no_break_comment | done (comment_text; a case right behind the open tag reported) | edit (comment insert) |
| no_closing_tag | done | edit (delete) |
| no_multiple_statements_per_line | done | gap, struct |
| no_space_around_double_colon | done | gap |
| no_spaces_after_function_name | done | gap |
| no_trailing_whitespace | done | gap |
| no_trailing_whitespace_in_comment | done | gap (comments are trivia) |
| single_blank_line_at_eof | done | gap |
| single_class_element_per_statement | done (elements) | edit (split) |
| single_line_after_imports | done (`use ... ?>` reported) | gap, struct |
| spaces_inside_parentheses | done (space=none; single refused) | gap |
| statement_indentation | done | gap, struct |
| switch_case_semicolon_to_colon | done | text |
| switch_case_space | done | gap |
| encoding | done (raw-file pre-pass) | whole file (BOM) |
| full_opening_tag | done (open tags; a short tag in inline HTML reported) | text |

## Added by @PER-CS2x0 / @PER-CS3x0

| rule | set | status | kind |
|---|---|---|---|
| cast_spaces | CS2 | done | gap |
| concat_space | CS2 | done | gap, struct |
| array_indentation | CS2 | done | gap, struct |
| array_syntax | CS2 | done (short; long refused) | edit (`array(` to `[`) |
| attribute_block_no_spaces | CS2 | done | gap |
| single_line_empty_body | CS2 | done | gap |
| trailing_comma_in_multiline | CS2 | done (elements, after_heredoc) | edit (insert `,`) |
| nullable_type_declaration | CS3 | done (question_mark; union refused) | edit (`?T` / `T\|null`) |
| operator_linebreak | CS3 | done (only_booleans, position) | edit (move operator) |
| ordered_types | CS3 | done (sort_algorithm, null_adjustment, case_sensitive) | edit (reorder) |
| types_spaces | CS3 | done (none, single) | gap |

@Symfony adds 89 rules (mostly phpdoc_*, edit rules); not planned yet.

## Edit phase (P3): the 15 rules that insert, delete, move or merge tokens

All of @PSR12 / @PER-CS2.0 / @PER-CS3.0 is implemented. Instructions over the 2.9 G floor (`--dry-run`, `/usr/bin/time -l`), with the
options the presets use; dirty5 = `bench/mutate_edit.php` (most of its files need the rule, so the cost there is mostly the re-parse of
every edited file, about one floor share per file):

| rule | corpus-src | dirty5 (files changed) |
|---|---|---|
| elseif | +0.07 | +0.06 (2) |
| no_closing_tag | +0.0 | +0.0 (1) |
| no_leading_import_slash | +0.02 | +1.8 (76) |
| single_trait_insert_per_statement | +0.0 | +0.2 (3) |
| new_with_parentheses (anonymous_class=false) | +0.0 | +0.7 (21) |
| control_structure_braces | +0.07 | +2.6 (192) |
| single_import_per_statement (group_to_single_imports=false) | +0.0 | +2.0 (78) |
| modifier_keywords | +0.05 | +1.1 (80) |
| single_class_element_per_statement | +0.05 | +0.9 (29) |
| array_syntax | +0.02 | +2.9 (154) |
| trailing_comma_in_multiline (PER-CS options) | +1.0 (32 files changed) | +2.1 (90) |
| nullable_type_declaration | +0.02 | +2.8 (78) |
| ordered_types (none, always_last) / (alpha default) | +0.0 / +0.45 | +2.3 / +2.4 |
| ordered_imports (none, class/function/const) / (alpha) | +0.05 / +1.5 (39 files changed) | +0.0 / +2.1 |
| ordered_class_elements (use_trait) / (default order) | +0.0 / +2.8 (71 files changed) | +0.0 / +2.7 |

Where the corpus-src column is large, the rule really changes those files (a re-parse each); the per-file decision is a scan of the Source.
Constructs inside a done rule that DO need it are refused or reported, never guessed:

| rule | construct | behaviour |
|---|---|---|
| `spaces_inside_parentheses`, `declare_equal_normalize` | `space=single` | refused at option parsing |
| `control_structure_continuation_position` | `position=next_line` | refused at option parsing (needs `detectIndent` of the keyword) |
| `types_spaces` | `space_multiple_catch` different from `space` | refused at option parsing |
| `function_declaration` | a trailing comma of a single-line signature / `use` list (upstream deletes the token) | file reported `not fixed`, left as it is (could use the edit phase now) |
| `single_line_after_imports` | `use X ?>` (upstream inserts a `;`) | same |
| `full_opening_tag` | a short `<? ` inside inline HTML (upstream turns it into an open tag) | same |
| `blank_line_after_namespace`, `single_line_after_imports` | a blank at the very end of the file behind the `;` (upstream throws on a missing token) | file left as it is, as upstream |
| `no_break_comment` | a `case` whose preceding token is the open tag (upstream rewrites the open tag) | file reported `not fixed`, left as it is |
| `class_definition` | a comment wedged between `final` / `readonly` / `abstract` without spaces (upstream's stale modifier index after an insertion) | not mirrored |
| `array_syntax`, `nullable_type_declaration`, `ordered_imports` | `syntax=long`, `syntax=union`, `sort_algorithm=length` | refused at option parsing |
| every edit rule | a result upstream's lint step rejects (it then leaves the file) | mirrored only where the construct is known: `no_closing_tag` after a closure / match / anonymous class body, `control_structure_braces` around an inner `if (..):` that has an `else`; otherwise native writes what upstream would have rejected |

Upstream quirks mirrored (found by the oracle): `switch_case_space` skips a whole switch when its `default` has no blank before the colon;
`attribute_block_no_spaces` collects ONE delete list for both sides of `#[ ]` and never visits a group nested in an outer one's arguments;
`NAMESPACE\X` is rewritten to `namespace\X` in every file a rule changed (`NameQualifiedTransformer` builds that token from a literal);
`class_definition` keeps upstream's stale token indexes after an insertion (the header is processed on a flat copy of upstream's token list);
`lowercase_keywords` also lowercases `Foo::CLASS` (a custom id over a T_STRING).

Edit-phase quirks mirrored: `elseif` drops the trivia item right after `else` even when it is a comment, and the whitespace after `if` when
a comment is then last; the blanks around a dropped `if` stay TWO whitespace tokens (`Source::wsSplit`, read by single_space_around_construct
and no_trailing_whitespace); `single_trait_insert_per_statement` clones whatever item precedes the first `use` (a comment, a code token);
`new_with_parentheses` scans raw kinds from `new` (`new $a->b` gets `()` at the end, attribute arguments' `(` stop the scan), seeing the
`()` it inserted for a later `new`; `trailing_comma_in_multiline` adds `, ` (comma + space) when nothing separates the last element and the
closer (stale end index); `nullable_type_declaration` rewrites `null|int $a, $b` of the second property to `?int$a,` (the range runs to the
comma) and keeps the first of two parameters with the same name; `ordered_types` / `nullable_type_declaration` overwrite the type's trivia
(overrideRange); `ordered_class_elements` does not visit classes nested in a class that had elements; `ordered_imports` / `single_import_per_statement`
skip the item right after `use` when looking for the import's start; `getClassyElements` takes `&` of `function &f` as the method name.
