# Rules roadmap

Computed from upstream 3.95.27 `RuleSet` (inclusions resolved: `@PER-CS3x0` > `@PER-CS2x0` > `@PER-CS1x0` > `@PSR12` > `@PSR2` > `@PSR1`).

| Set | rules | delta |
|---|---|---|
| @PSR12 | 51 | base (40 done, 11 todo) |
| @PER-CS2x0 | 58 | +7 new rules vs @PSR12, 4 option changes, 0 dropped; 45 done, 13 todo |
| @PER-CS3x0 | 62 | +4 new rules vs CS2x0 (+11 vs @PSR12), 1 option change; 47 done, 15 todo |
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
| blank_line_after_opening_tag | done (file rule) | gap |
| blank_line_between_import_groups | done | gap, struct |
| blank_lines_before_namespace | done (min/max_line_breaks) | gap |
| braces_position | done | gap, struct |
| class_definition | done (every option) | gap, struct |
| compact_nullable_type_declaration | done | gap |
| declare_equal_normalize | done (space=none; single refused) | gap |
| lowercase_cast | done | text |
| lowercase_static_reference | done | text |
| modifier_keywords | needs edit phase (moves / inserts modifiers) | edit (reorder) |
| new_with_parentheses | needs edit phase (inserts / deletes `()`) | edit (insert `()`) |
| no_blank_lines_after_class_opening | done | gap |
| no_extra_blank_lines | done | gap, struct |
| no_leading_import_slash | needs edit phase (deletes `\`) | edit (delete) |
| no_whitespace_in_blank_line | done | gap |
| ordered_class_elements | needs edit phase (moves members) | edit (move) |
| ordered_imports | needs edit phase (moves imports) | edit (move) |
| return_type_declaration | done (space_before none, one) | gap |
| short_scalar_cast | done | text |
| single_import_per_statement | needs edit phase (splits a statement) | edit (insert) |
| single_space_around_construct | done | gap, struct |
| single_trait_insert_per_statement | needs edit phase (splits a statement) | edit |
| ternary_operator_spaces | done | gap, struct |
| unary_operator_spaces | done | gap |
| blank_line_after_namespace | done | gap |
| constant_case | done (lower, upper) | text |
| control_structure_braces | needs edit phase (inserts braces) | edit (insert braces) |
| control_structure_continuation_position | done (same_line; next_line refused) | gap |
| elseif | needs edit phase (deletes `if` and the blank) | edit (token replace) |
| function_declaration | done (all options; a deleted trailing comma is reported) | gap |
| indentation_type | done | gap |
| line_ending | done | gap |
| lowercase_keywords | done | text |
| method_argument_space | done | gap, struct |
| no_break_comment | done (comment_text; a case right behind the open tag reported) | edit (comment insert) |
| no_closing_tag | needs edit phase (deletes `?>`, may insert `;`) | edit (delete) |
| no_multiple_statements_per_line | done | gap, struct |
| no_space_around_double_colon | done | gap |
| no_spaces_after_function_name | done | gap |
| no_trailing_whitespace | done | gap |
| no_trailing_whitespace_in_comment | done | gap (comments are trivia) |
| single_blank_line_at_eof | done | gap |
| single_class_element_per_statement | needs edit phase (splits a statement) | edit (split) |
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
| array_syntax | CS2 | needs edit phase (`array(` to `[`) | edit (`array(` to `[`) |
| attribute_block_no_spaces | CS2 | done | gap |
| single_line_empty_body | CS2 | done | gap |
| trailing_comma_in_multiline | CS2 | needs edit phase (inserts `,`) | edit (insert `,`) |
| nullable_type_declaration | CS3 | needs edit phase (`?T` <-> `T|null` rewrites the type tokens) | edit (`?T` / `T\|null`) |
| operator_linebreak | CS3 | done (only_booleans, position) | edit (move operator) |
| ordered_types | CS3 | needs edit phase (moves type tokens) | edit (reorder) |
| types_spaces | CS3 | done (none, single) | gap |

@Symfony adds 89 rules (mostly phpdoc_*, edit rules); not planned yet.

## Wave 3 (this file's `done` rows above): what is left and why

Every rule still `todo` inserts, deletes, moves or merges TOKENS, which the model (immutable `kind[]`, trivia in the gaps) cannot express without
the rebuild phase of `docs/design.md`: **needs edit phase** — `elseif`, `no_closing_tag`, `new_with_parentheses`, `control_structure_braces`,
`nullable_type_declaration`, `array_syntax`, `trailing_comma_in_multiline`, `ordered_types`, `modifier_keywords`,
`no_leading_import_slash`, `ordered_class_elements`, `ordered_imports`, `single_import_per_statement`, `single_trait_insert_per_statement`,
`single_class_element_per_statement`. (`short_scalar_cast`, `lowercase_*`, `constant_case`, `switch_case_semicolon_to_colon`, `full_opening_tag` and
`class_definition`'s modifier sort only change the BYTES of a token that stays where it is: text rules. `no_break_comment` inserts / removes COMMENTS and
`operator_linebreak` moves an operator across TRIVIA, never across a code token: both are gap rules, run on a `FlatTokens` window.)

Constructs inside a done rule that DO need it are refused or reported, never guessed:

| rule | construct | behaviour |
|---|---|---|
| `spaces_inside_parentheses`, `declare_equal_normalize` | `space=single` | refused at option parsing |
| `control_structure_continuation_position` | `position=next_line` | refused at option parsing (needs `detectIndent` of the keyword) |
| `types_spaces` | `space_multiple_catch` different from `space` | refused at option parsing |
| `function_declaration` | a trailing comma of a single-line signature / `use` list (upstream deletes the token) | file reported `not fixed`, left as it is |
| `single_line_after_imports` | `use X ?>` (upstream inserts a `;`) | same |
| `full_opening_tag` | a short `<? ` inside inline HTML (upstream turns it into an open tag) | same |
| `blank_line_after_namespace`, `single_line_after_imports` | a blank at the very end of the file behind the `;` (upstream throws on a missing token) | file left as it is, as upstream |
| `no_break_comment` | a `case` whose preceding token is the open tag (upstream rewrites the open tag) | file reported `not fixed`, left as it is |
| `class_definition` | a comment wedged between `final` / `readonly` / `abstract` without spaces (upstream's stale modifier index after an insertion) | not mirrored |

Upstream quirks mirrored (found by the oracle): `switch_case_space` skips a whole switch when its `default` has no blank before the colon;
`attribute_block_no_spaces` collects ONE delete list for both sides of `#[ ]` and never visits a group nested in an outer one's arguments;
`NAMESPACE\X` is rewritten to `namespace\X` in every file a rule changed (`NameQualifiedTransformer` builds that token from a literal);
`class_definition` keeps upstream's stale token indexes after an insertion (the header is processed on a flat copy of upstream's token list);
`lowercase_keywords` also lowercases `Foo::CLASS` (a custom id over a T_STRING).
