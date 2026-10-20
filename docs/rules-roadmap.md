# Rules roadmap

Computed from upstream 3.95.27 `RuleSet` (inclusions resolved: `@PER-CS3x0` > `@PER-CS2x0` > `@PER-CS1x0` > `@PSR12` > `@PSR2` > `@PSR1`).

| Set | rules | delta |
|---|---|---|
| @PSR12 | 51 | base (51 done) |
| @PER-CS2x0 | 58 | +7 new rules vs @PSR12, 4 option changes, 0 dropped; 58 done |
| @PER-CS3x0 | 62 | +4 new rules vs CS2x0 (+11 vs @PSR12), 1 option change; 62 done |
| @Symfony | 151 | +89 vs CS3x0 (superset; all 151 done: no_unused_imports, the phpdoc rules below, ...) |

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

@Symfony adds 89 rules (mostly phpdoc_*, edit rules); done: `no_unused_imports` (+0.27 G on corpus-src for the files it leaves, +2.2 G for the
33 it changes: their analysis, a whole-file FlatTokens surgery and the re-parse).

## PHPDoc rules (@Symfony / @PhpCsFixer)

Docblocks are trivia, so these are gap rules over the doc pieces of a gap (`DocRule`, README "PHPDoc rules"); a docblock is parsed only when the
rule's text test passes. Every option set @Symfony / @PhpCsFixer use was run through the oracle (corpus-src, docsrc, dirty2, dirty6, dirty7,
tests/cases; the name rules also dirty8 / dirty9 = `bench/mutate_imports.php`; `IMPORTS=1 bench/doc.sh`), and the fused preset slices
over every corpus. Instructions over the ~3.05 G floor on corpus-src (`--dry-run`):

| rule | options run | kind | cost |
|---|---|---|---|
| phpdoc_no_access, phpdoc_no_package, general_phpdoc_annotation_remove | default | doc text (annotation removed, empty docblock dropped) | +0.05 |
| general_phpdoc_tag_rename | `{inheritDocs: inheritDoc}`, default | doc text (upstream regex) | +0.05 |
| phpdoc_no_alias_tag | @Symfony (+const), default | doc text | +0.1 |
| phpdoc_inline_tag_normalizer, phpdoc_tag_type | default, `{inheritDoc: inline}` | doc text | +0.1 |
| phpdoc_trim, phpdoc_trim_consecutive_blank_line_separation | default | doc text / lines | +0.15 / +0.4 |
| phpdoc_single_line_var_spacing | default | any `/**` comment (a `/**@var` T_COMMENT becomes a docblock) | +0.05 |
| phpdoc_var_annotation_correct_order, phpdoc_annotation_without_dot, phpdoc_summary | default | doc text / annotations | +0.05 / +0.25 / +0.4 |
| no_empty_phpdoc | default | gap (removes the docblock and its line; an emptied token blocks the next one, as upstream) | +0.1 |
| phpdoc_scalar, phpdoc_types, phpdoc_types_order | @Symfony and default sets | annotation types (TypeExpression) | +0.2 / +0.5 / +0.4 |
| align_multiline_comment | phpdocs_only, phpdocs_like, all_multiline | gap (indent of the line) | +0.3 |
| phpdoc_indent, no_blank_lines_after_phpdoc | default | gap + next token | +0.25 / +0.05 |
| phpdoc_no_useless_inheritdoc, php_unit_fqcn_annotation | default | file rule (class walk) | +0.0 |
| phpdoc_var_without_name | default | doc + next token kinds | +0.05 |
| phpdoc_order, phpdoc_separation | @Symfony (order / groups) and defaults, skip_unlisted_annotations | doc lines | +0.2 / +0.4 |
| phpdoc_return_self_reference | default, custom replacements | file rule (classy elements) | +0.2 |
| phpdoc_align | vertical, left, custom tags + per-tag spacing | doc lines (upstream's column regexes) | +0.75 |
| phpdoc_to_comment | @Symfony (allow_before_return_statement=false), true + ignored_tags | doc + CommentsAnalyzer on the next tokens | +0.1 |
| no_superfluous_phpdoc_tags | @Symfony, @PhpCsFixer (allow_mixed), default | file rule (namespace, imports, current class) | +0.85 |
| fully_qualified_strict_types | @Symfony (default), @PhpCsFixer (import_symbols), leading_backslash | edit rule (name spans, imports inserted) | +3.0 (82 files changed; +0.4 on upstream's src) / +6 (136 changed) |
| global_namespace_import | @Symfony / @PhpCsFixer (all false), null mixes | edit rule (`\` inserted) + doc types | +0.1 |

Quirks mirrored: `Annotation::setTypes` passes the new type as a PCRE replacement (`$` / `\` in it are not escaped); `no_empty_phpdoc` throws on
an empty docblock that ends the file behind a line break (file left); `phpdoc_no_useless_inheritdoc` visits an anonymous `new class` inside a
class body as a classy of its own and a class without `{` throws (file left); `php_unit_fqcn_annotation` starts at the last docblock before the
test class's `{`, wherever it is; `phpdoc_var_without_name` skips promoted constructor parameters (their modifiers are custom tokens); `phpdoc_order` appends / prepends a whole
annotation to one LINE of its neighbour (the start line keeps `/**` when the moved annotation was on it); `phpdoc_align` leaves a group that runs
into the last line of the docblock; `no_superfluous_phpdoc_tags` writes `/**  */` for a docblock it emptied and throws (file left) when no
namespace scope holds a documented element; `fully_qualified_strict_types`: see README "PHPDoc rules" (cleared tokens, stale attribute
indexes, the `catch` that stops after a shrink); `global_namespace_import` with `import_* = true` (the option default, used by no set) is not
ported: such a file is reported not fixed.

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

## @Symfony, the non-phpdoc rules (65)

All byte-identical to upstream under Zend (`bench/sym.sh <rules>`: clean corpus, `dirty`, `dirty2`, `dirty3`, `dirty6` = `bench/mutate_sym.php`, `tests/cases`; every option set the
sets use). Kinds: **text** = `TextRule`, **gap** = trivia only, **edit** = `EditRule` (Source edits, re-parse), **flat** = `FlatRule` (upstream's algorithm on a whole-file
`FlatTokens`; `gapsOnly` rules hand the result back as gaps, no re-parse). Cost = instructions over the ~3.0 G floor on corpus-src (`bench/cost.sh`); where a rule really
changes files (the corpus is not Yoda, uses `$i++`, ...) the cost is mostly the re-parse of those files.

| family | rules | notes |
|---|---|---|
| casing (text) | magic_constant_casing, integer_literal_case, magic_method_casing, native_type_declaration_casing, native_function_casing, class_reference_name_casing | the two last use `NativeNames` (generated by `bench/gen_names.php` from the oracle php's internal functions / classes) |
| alias / operators (text, edit) | no_alias_language_construct_call, standardize_not_equals, no_short_bool_cast, no_unset_cast, no_binary_string, no_mixed_echo_print, no_useless_nullsafe_operator, switch_continue_to_break, standardize_increment, increment_style, yoda_style | increment_style 6.8 -> 4.2 G (pre style on the Source); yoda_style +4.3 G (188 of 276 files change) |
| spacing (gap) | no_singleline_whitespace_before_semicolons, space_after_semicolon, object_operator_without_whitespace, no_spaces_around_offset, trim_array_spaces, no_whitespace_before_comma_in_array, whitespace_after_comma_in_array, no_multiline_whitespace_around_double_arrow, declare_parentheses, no_leading_namespace_whitespace, type_declaration_spaces, blank_line_before_statement, class_attributes_separation | the last three are flat `gapsOnly` (+0.05 / +0.9 / +0.9 G) |
| comments (gap, flat) | single_line_comment_spacing, single_line_comment_style, no_empty_comment | |
| deletions (edit, flat) | no_empty_statement, no_unneeded_braces, no_unneeded_import_alias, no_trailing_comma_in_singleline, no_useless_return, no_useless_else, no_unneeded_control_parentheses, lambda_not_used_import, no_null_property_initialization, empty_loop_body, empty_loop_condition, no_alternative_syntax, include, semicolon_after_instruction, echo_tag_syntax (long), single_line_throw | clearing a token with its whitespace merged is, as text, deleting the token unit |
| strings | single_quote, simple_to_complex_string_variable, backtick_to_shell_exec, no_useless_concat_operator | |
| other | protected_to_private, php_unit_method_casing, nullable_type_declaration_for_default_null_value (use_nullable_type_declaration=true), linebreak_after_opening_tag, normalize_index_brace, clean_namespace (a no-op on PHP 8, as upstream) | |

Options refused at parsing: `nullable_type_declaration_for_default_null_value` `use_nullable_type_declaration=false`, `echo_tag_syntax` `format=short`, `yoda_style` `always_move_variable=true`, `no_useless_concat_operator` `juggle_simple_strings=true`.

Upstream quirks mirrored (found by the oracle): upstream's lint step rejects some results and leaves the whole file (`Source::abort`): `use A\ {B}` group braces removed
(`use A\ B`), an unbraced `do stmt; while (x)` turned `{}` / `for (;;)`, `while (true): ... endwhile` turned into `for (;;):`, `public $a = null ?? 1;` losing its `=`,
`&$a === 1` swapped to `&1 === $a`, `no_empty_comment` fusing two words (`public/**/const`; a statement keyword before an operand still lints, a modifier before a type inside a
parameter list does; modelled in `NoEmptyComment::glueRejected`), `class_attributes_separation` with `trait_import: none` and no element above (an `assert` fails upstream);
`type_declaration_spaces` visits functions first and class elements after with indexes taken up front (an insertion in a function shifts the stale index of a later property);
`TokensAnalyzer`-style `equals('&')` is also true for `T_AMPERSAND_*`; `Preg::match('/^\h+$/')` matches blanks plus one trailing "\n"
(`whitespace_after_comma_in_array` ensure_single_space); `no_null_property_initialization` continues a property list only when a comma follows the `null` directly.

Known divergences: `backtick_to_shell_exec` on empty backticks (upstream builds two `"` tokens, so `single_quote` leaves `""`; re-lexed it is one string and becomes `''`); an empty
comment made only of Unicode spaces is not found by `no_empty_comment`'s prefilter; whitespace tokens that upstream leaves adjacent after a `clearAt` (not a merge) are reported
through `wsSplit` where `increment_style`, `no_alternative_syntax` and the flat rules edit (the rules that read it: `no_trailing_whitespace`, `no_extra_blank_lines`, `no_singleline_whitespace_before_semicolons`, `single_line_comment_style`, the phpdoc rules); two COMMENT tokens that upstream leaves adjacent after `no_unused_imports` removed a statement between them (`// c` then `/* c */`) lex as one line comment in the next pass, so `single_line_comment_style` (via @PhpCsFixer) misses the second; `function_declaration` still refuses a trailing comma in a single-line signature or `use` list (`trailing_comma_single_line=false` clears the comma: needs the edit phase; reported as an error, not a wrong output; the repro lines live in `tests/cases-fd/`, outside the parity matrix); `native_function_casing` / `class_reference_name_casing` know the internal names of the php that generated `NativeNames`.
Compiler issues met: #371 (the tokenizer splits the binary flag of `b'x'` off, handled in `no_binary_string`, `no_useless_concat_operator`, `yoda_style`), #372 (`preg_*` with `/u` on
invalid UTF-8: `Trivia::pmatch` / `preplace` check the subject first).

## Presets: rules not implemented yet (`manticore-fmt list-sets` prints all 101)

| set | rules | implemented | missing |
|---|---|---|---|
| @PSR1 / @PSR2 / @PSR12 | 2 / 31 / 51 | all | 0 |
| @PER-CS (= 3.0) / @PER-CS2.0 / @PER-CS1.0 | 62 / 58 / 51 | all | 0 |
| @Symfony | 151 | 151 | 0 |
| @PhpCsFixer | 171 | 151 | 20 |
| @Symfony:risky / @PhpCsFixer:risky | 40 / 50 | 0 | 40 / 50 |
| @PER-CS:risky, @PSR12:risky | 2 | 0 | 2 |
| @PHP7x4Migration ... @PHP8x5Migration | 11 ... 18 | 5-6 | 6-12 |
| @PHPUnit*Migration:risky | 1-8 | 0 | all |

`manticore-fmt list-rules --unimplemented` is the to-do list; @Symfony misses nothing.

Adding a rule: `src/Rule/<Name>.php` (a `GapRule` / `EditRule`), one `if ($name === '...')` branch in `Rules::make` (that is the registry:
the not-implemented check, `list-rules`, `list-sets` and the presets all ask it), a case in `tests/main.php` or `tests/cases`, and an
oracle run with the options the sets use. Nothing else: the set table, the aliases and the option metadata come from upstream.