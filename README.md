# manticore-fmt

A PHP formatter written in PHP, compiled natively by Manticore. php-cs-fixer's rules are the specification: same rule
names, byte-identical output per rule against upstream 3.95.27 under Zend (the oracle). See `docs/design.md`.

Status: **P3 edit phase** — core, custom tokens (`ct[]`) + structure columns, the edit phase, the docblock model, **all 300 of upstream's rules** (`bin/manticore-fmt list-rules`: 300 implemented, 0 not): @PSR12 51 of 51, @PER-CS2.0 58 of 58, @PER-CS3.0 62 of 62, @Symfony 151 of 151, @PhpCsFixer 171 of 171, and all 76 of upstream's risky rules (`--allow-risky=yes`): @Symfony:risky 40 of 40, @PhpCsFixer:risky 50 of 50, every `:risky` set of @PSR12 / @PER / @PHP*Migration / @PHPUnit*Migration complete.

- gaps and whitespace: `no_trailing_whitespace`, `line_ending`, `no_whitespace_in_blank_line`, `indentation_type`, `single_blank_line_at_eof`, `no_extra_blank_lines`
  (every `tokens` value), `braces_position` (every option), `statement_indentation`, `cast_spaces`, `ternary_operator_spaces`, `unary_operator_spaces`,
  `method_argument_space`, `binary_operator_spaces` (no align* strategies: refused), `concat_space`, `single_space_around_construct`;
- wave 3, text rules (a token's bytes, same token count): `lowercase_keywords`, `lowercase_cast`, `short_scalar_cast`, `lowercase_static_reference`,
  `constant_case`, `switch_case_semicolon_to_colon`, `full_opening_tag`; pre-parse: `encoding`;
- wave 3, gap rules: `no_space_around_double_colon`, `no_spaces_after_function_name`, `spaces_inside_parentheses` (`space=none`),
  `declare_equal_normalize` (`space=none`), `return_type_declaration`, `compact_nullable_type_declaration`, `types_spaces`,
  `attribute_block_no_spaces`, `switch_case_space`, `single_line_empty_body`, `no_trailing_whitespace_in_comment`,
  `control_structure_continuation_position` (`same_line`), `no_blank_lines_after_class_opening`, `blank_lines_before_namespace`,
  `blank_line_after_namespace`;
- wave 3, whole-file rules: `blank_line_after_opening_tag`, `function_declaration`, `class_definition`, `single_line_after_imports`,
  `array_indentation`, `no_multiple_statements_per_line`, `blank_line_between_import_groups`, and on a `FlatTokens` window (upstream's trivia
  inserts / moves ported line by line) `no_break_comment`, `operator_linebreak`;
- edit phase (tokens inserted, deleted, replaced, moved): `elseif`, `no_closing_tag`, `no_leading_import_slash`, `single_trait_insert_per_statement`,
  `new_with_parentheses`, `control_structure_braces`, `single_import_per_statement`, `modifier_keywords`, `single_class_element_per_statement`,
  `array_syntax` (short; long refused), `trailing_comma_in_multiline`, `nullable_type_declaration` (question_mark; union refused), `ordered_types`,
  `ordered_imports` (none, alpha; length refused), `ordered_class_elements` (none, alpha); from @Symfony: `no_unused_imports`;
- phpdoc (docblocks are trivia: gap rules over the doc pieces of a gap, see "PHPDoc rules"): `phpdoc_no_access`, `phpdoc_no_package`,
  `general_phpdoc_annotation_remove`, `general_phpdoc_tag_rename`, `phpdoc_no_alias_tag`, `phpdoc_inline_tag_normalizer`, `phpdoc_tag_type`, `phpdoc_trim`,
  `phpdoc_trim_consecutive_blank_line_separation`, `phpdoc_single_line_var_spacing`, `phpdoc_var_annotation_correct_order`, `phpdoc_annotation_without_dot`,
  `phpdoc_summary`, `no_empty_phpdoc`, `phpdoc_scalar`, `phpdoc_types`, `phpdoc_types_order`, `align_multiline_comment` (every comment_type), `phpdoc_indent`,
  `no_blank_lines_after_phpdoc`, `phpdoc_order`, `phpdoc_separation`, `phpdoc_align` (vertical / left, per-tag spacing); reading the code around the docblock:
  `phpdoc_no_useless_inheritdoc`, `phpdoc_var_without_name`, `php_unit_fqcn_annotation`, `phpdoc_return_self_reference`, `phpdoc_to_comment`,
  `no_superfluous_phpdoc_tags`; names in code and docblocks (edit rules): `fully_qualified_strict_types` (every option, import_symbols included),
  `global_namespace_import` (import_* false / null; `true`, importing, reports the file as not fixed).

- the 20 rules @PhpCsFixer adds to @Symfony (`docs/rules-roadmap.md`, "The 20 @PhpCsFixer rules"): `no_whitespace_in_empty_array`, `multiline_comment_opening_closing` (gap);
  `explicit_indirect_variable`, `explicit_string_variable` (edit), `heredoc_to_nowdoc`, `string_implicit_backslashes` (every option), `self_static_accessor` (text); on a `FlatTokens` window (upstream's
  algorithm line by line, stale indexes included): `combine_consecutive_issets`, `combine_consecutive_unsets`, `no_superfluous_elseif`, `multiline_whitespace_before_semicolons` (both strategies),
  `method_chaining_indentation`, `return_assignment` (`skip_named_var_tags`); docblocks: `phpdoc_add_missing_param_annotation` (`only_untyped`), `phpdoc_no_duplicate_types`, `phpdoc_no_empty_return`,
  `phpdoc_order_by_value` (`annotations`); PHPUnit test classes (`PhpUnitClassRule`, also the base of `php_unit_method_casing`): `php_unit_internal_class` (`types`),
  `php_unit_test_class_requires_covers`, `php_unit_data_provider_method_order` (`placement`).
- the risky rules (`docs/rules-roadmap.md`, "Risky rules"; run only with `--allow-risky=yes` / `"risky": true`, which the oracle scripts always pass): 76 rules, upstream's algorithm line by line on a whole-file `FlatTokens` (`FlatRule` helpers:
  `findCall` / `isGlobalCall` (AbstractFunctionReferenceFixer + FunctionsAnalyzer), `callArguments`, `overrideRange`, `findSeq` (Tokens::findSequence), `blockEdge`, `rangeEquals`; `FlatAnalyzer` = TokensAnalyzer's operator tests; `CommentsAnalyzer` is shared with `phpdoc_to_comment`),
  text rules where a token only changes (`logical_operators`, `strict_comparison`, `string_line_ending`, `no_alias_functions`, `no_homoglyph_names`, `mb_str_functions`), PHPUnit rules on `PhpUnitClassRule`
  (`dataProviders()` = DataProviderAnalyzer). Each rule decides on the untouched Source whether it can change a file at all (`candidate()`), so a clean tree stays at the floor. What upstream asks its running interpreter is answered with the oracle's:
  `class_keyword` (does a class exist) uses `NativeNames::CLASS_LIKE` (the internal classes of the oracle PHP + what upstream's composer autoloader can load), `phpdoc_to_{param,property,return}_type` (does the new type parse) use `TypeSyntax` (generated from
  the real tokenizer by `bench/gen_type_syntax.php`: the types are names joined by `|`, only reserved words matter).
- the last 51 (`docs/rules-roadmap.md`, "The last wave"): the deprecated names run through upstream's own proxy semantics (`Aliases`: successor, forwarded or fixed options, the proxy's own sort slot
  `slotPriority` / `slotName`, so `no_blank_lines_before_namespace` (0) and `no_trailing_comma_in_singleline_function_call` (3) run where upstream runs them, not at their successor's priority); `braces` expands into
  its eight fixers (`statement_indentation` gets upstream's `bracesFixerCompatibility`, `control_structure_continuation_position` its `next_line` mode); attributes (`attribute_empty_parentheses`, `general_attribute_remove`,
  `ordered_attributes` (alpha / custom, imports resolved), `php_unit_attributes`); heredocs (`heredoc_indentation`, `heredoc_closing_marker`, `multiline_string_to_heredoc`); `numeric_literal_separator`,
  `octal_notation`, `list_syntax`, `group_import`, `header_comment`, `ordered_interfaces`, `multiline_promoted_properties`, `simplified_if_return`, `simplified_null_return`, `ternary_to_null_coalescing`,
  `assign_null_coalescing_to_coalesce_equal`, `return_to_yield_from`, `stringable_for_to_string`, `no_redundant_readonly_property`, `new_expression_parentheses`, `not_operator_with_space` /
  `not_operator_with_successor_space`, `class_keyword_remove`; docblocks: `phpdoc_line_span`, `phpdoc_param_order`, `phpdoc_tag_no_named_arguments`, `phpdoc_tag_casing`, `php_unit_size_class`; and the four
  `doctrine_annotation_*` rules (`Doc\DoctrineTokens` = upstream's DocLexer + Tokens). All on the same machinery as before (text / gap rules, `FlatRule` ports with upstream's indexes and quirks). Where upstream
  throws, or its result does not lint (a heredoc re-indented with tabs and spaces mixed, `E::A::class` -> `E::'A'`, the last `@param` ending on the closing line, ...), the file is left as it is (`Source::abort`).
  Two checks upstream's `FixerFactory` / `ConfigurationResolver` make that the CLI lacked now exist: conflicting fixers (`blank_lines_before_namespace` with its deprecated twins, `single_import_per_statement`
  with `group_import`: exit 1, upstream's message) and options given to a rule that has none (`Is not configurable.`, exit 32).
A preset with a rule that is not implemented fails naming it (`docs/rules-roadmap.md`); since every upstream rule exists, only the `@auto*` sets (they read composer.json) are refused.
A file with a T_BAD_CHARACTER is skipped, as upstream skips a file that does not lint. A file on which upstream throws (a blank at the very end
of the file behind `namespace X;` / `use X;`) is left as it is, with a notice; so is a file on which a rule would need the edit phase
(`function_declaration` deleting a trailing comma of a single-line signature, `single_line_after_imports` on `use ... ?>`): `   ! file: not fixed, ...`.

## Build

```bash
manticore build manticore.json     # bin/manticore-fmt (the CLI) and bin/fmt-test (tests); ~10 s
```

## Use

```bash
bin/manticore-fmt fix|check [--config=FILE] [--rules=<csv|@PSR12|JSON>] [--diff] [--format=txt|json|checkstyle|junit|gitlab] [paths...]
bin/manticore-fmt list-rules | list-sets | describe <rule|@set> | resolve | help
```

A drop-in for `php-cs-fixer fix` on the rules implemented: `.manticore-fmt.{json,yaml,yml}` (+ `.dist`) instead of `.php-cs-fixer.dist.php`,
every upstream set (`@PSR12`, `@PER-CS3.0`, `@Symfony`, `@PHP8x5Migration`, `:risky` ...) and every upstream rule alias, upstream's
options and exit-code bitmask (0 / 4 / 8 / 16 / 32 / 64), a content-hash cache and a fork pool (one worker per core by default).
A set with a rule that is not implemented fails naming all of them; `--allow-unimplemented` skips them with one notice.
`indent` (tabs) and `lineEnding` (CRLF) are configurable and byte-identical to upstream. Everything, with the translation of a PHP
config and the known divergences: **`docs/config.md`**.
## Test

```bash
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src      # 276 files, owned by the fork
bin/fmt-test $C                    # round trip (join(tokens, gaps) == input) on every file, gaps hold only trivia,
                                   # Structure invariants, hand-written rule cases
```

## Token parity (kinds, custom kinds, boundaries vs upstream `Tokens::fromCode`)

```bash
bash bench/tokens.sh        # bench/dump_tokens.php (Zend, upstream) vs `bin/manticore-fmt dump-tokens` on tests/syntax + the corpus
```

`tests/syntax/*.php` are hand-written PHP 8.1-8.5 cases (enums, readonly, attributes, match, named args, first-class
callables, nullsafe, property hooks, asymmetric visibility, pipe, heredoc, alternative syntax, DNF types, ...).

## Oracle (byte parity vs upstream under Zend)

```bash
php -d xdebug.mode=off bench/oracle.php <corpus> <rules>      # prints `IDENTICAL: N changed files`
```

It copies the corpus twice, runs upstream `fix` (Zend, sequential) and `bin/manticore-fmt fix` for real, then compares
every file byte for byte (upstream's exit 64, a result it rejected by linting and left alone, is accepted: that file must be left alone natively too). Upstream lives in the fork (`bench/work/upstream`); nothing is copied.

The clean corpus changes nothing under these rules, so it proves little. Use the dirtied corpora and the edge cases too (`bench/rule.sh <rules>...` runs all of them in parallel — every corpus, `bench/work/ut` = every 4th test of upstream's own PHPUnit suite, `tests/cases`, `tests/syntax` — with the binary under `bench/gfmt.sh`, an RSS watchdog: kill -9 above 2 GB or 60 s, `CORPORA="dirs"` limits it; `bench/combo.sh` is the quiet variant;
`FMT_BIN=<copy of the binary>` lets a long run survive a rebuild; `php bench/now.php @PSR12` prints the --rules JSON of the implemented slice of a preset):

```bash
php bench/mutate_space.php $C bench/work/dirty2   # spaces, tabs, newlines, comments between tokens, statements joined on one line
php bench/mutate_case.php $C bench/work/dirty3    # random case of keywords / true / false / null / self / casts, `case x;`, `elseif` -> `else if`
php bench/mutate_fall.php $C bench/work/dirty4   # deleted break / return / continue statements, `no break` comments of several shapes (needs a corpus with switches)
php bench/mutate_edit.php $C bench/work/dirty5   # edit-rule dirt: braces dropped, long arrays, trailing commas dropped, T|null, merged imports / trait uses / properties, swapped modifiers, var
php bench/mutate_phpunit.php bench/work/ut bench/work/ut2   # PHPUnit dirt (php_unit_*): @internal / @covers dropped, class docblocks emptied; bench/work/ut3 = ut with every data provider moved before its test
php bench/mutate.php $C bench/work/dirty     # trailing blanks, CRLF/mixed endings, odd \R bytes, bad UTF-8 ... (gitignored)
php -d xdebug.mode=off bench/oracle.php bench/work/dirty no_trailing_whitespace,line_ending,no_whitespace_in_blank_line
php -d xdebug.mode=off bench/oracle.php tests/cases line_ending      # hand-written edge cases (open tag, heredoc, inline HTML, EOF)
```

The @Symfony non-phpdoc rules have their own helpers: `bench/mutate_sym.php` (dirt per family: casing, aliases, spaces, commas, comments, empty statements / loops, increments, parentheses, concatenation, closures; `dirty6`),
`bench/sym.sh <rules>` (the oracle on the clean corpus, `dirty*`, `tests/cases`), `bench/fires.sh` (how many files each corpus makes a rule change: does the dirt reach it?), `bench/cost.sh` (instructions over the floor),
`bench/guard.sh <seconds> <cmd>` (kills a command at 2 GB RSS or the time limit), `bench/build.sh`, `bench/gen_names.php` (internal function / class / constant names: the casing rules, `native_function_invocation` `@internal`, `native_constant_invocation`).

The risky rules: the oracle and `bench/cost.sh` always pass `--allow-risky=yes` to both sides; `bench/mutate_risky.php` makes their dirt (`dirty11` = `untype`: type declarations dropped for `phpdoc_to_*_type`; `dirty10` from the corpus: one family per rule group, plus `ut4` / `ut5` from the PHPUnit tests: `bench/mutate_new.php` makes the dirt of the last wave (`dirty12` from corpus-src, `dirty12d` from docsrc).
assertions, `->will($this->returnValue())`, `test*` methods turned into `@test`, renamed / non-static data providers); `bench/rr.sh <spec>...` is `rule.sh` condensed to one line per spec, `bench/batch.sh <out> <spec>...` runs several into a file (for a background run),
`bench/linediff.php <case> <rules>` runs every line of a case file on its own and prints the lines where the two sides differ, `bench/psr.sh` checks `psr_autoloading` (it needs the real file path: `ORACLE_SAMEDIR=1` runs both fixers in the same directory, `@WORK@` in the rules is that directory;
`tests/psr`). Dev scripts must not rebuild while a run is going: copy the binary (`FMT_BIN=/tmp/copy`).

## Bench

```bash
bench/ins.sh <corpus> <rules>      # instructions retired / wall / RSS, native vs the fork (macOS /usr/bin/time -l)
```

The floor (parse + engine, no rule doing work) is ~2.9 G instructions on corpus-src (a rule whose `prepare()` says no); every wave-3 rule costs
under 0.25 G on top of it (`/usr/bin/time -l bin/manticore-fmt fix --dry-run --rules=<rule> <corpus>`). Edit rules cost under 0.1 G on the clean
corpus with the options the presets use; an edited file costs one render + patch (about a third of a parse of it), so on the dirtied corpora a
rule that edits most files costs about +0.2..1.4 G (`docs/rules-roadmap.md` has the table). Whole presets on corpus-src (sequential
`--dry-run`, Apple M-series): @Symfony 30.3 G instructions / 2.5 s / ~120 MB peak RSS, @PhpCsFixer 40.4 G / 2.9 s / ~207 MB (35.0 G before its 20 extra rules: `docs/rules-roadmap.md` has their costs).

## Layout

| | |
|---|---|
| `src/Source.php` | lex once (`__McTok`, TOKEN_PARSE) into `kind[] start[] end[]` + `gapBefore[]`; token text is a substr; live `isLineStart(i)` / `lineIndent(i)` |
| `src/CT.php`, `src/Transform.php` | cs-fixer's custom token kinds as a second column `ct[]` (0 = none) + `pair[]`; port of `Tokenizer\Transformer\*` in upstream order |
| `src/Structure.php` | `pair[] depth[] parent[] owner[] sep[]` (owner kind of every bracket, comma chains) and statement segments |
| `src/GapRule.php`, `src/Engine.php` | gap rules in cs-fixer priority order (equal priorities: by rule name, as upstream); fusion by footprint |
| `src/EditRule.php`, `src/Edit.php` | edit rules: span edits over stream units (unit 2i = gap i, 2i+1 = token i), the Engine re-parses the file after a rule that recorded one (see "Edit phase") |
| `src/TypeWalk.php` | upstream's type collectors (arguments, return / property types) and `overrideRange` over a whole-file `FlatTokens` |
| `src/Gap.php` | trivia helpers: cs-fixer's view of a gap, PCRE `\R` semantics |
| `src/FlatTokens.php` | a window of code tokens + trivia pieces with upstream's integer indexes (insert / clear / move trivia), stored back as gaps |
| `src/Analyzer.php` | shared ports of upstream analyzers: isLambda, isAnonymousClass, switch colons, IndentationTrait's line indentation |
| `src/Doc/*.php`, `src/DocRule.php` | the docblock model (ports of upstream `DocBlock\*`: DocBlock, Line, Annotation, Tag, TypeExpression) and the phpdoc gap-rule base |
| `src/PhpUnitClassRule.php` | AbstractPhpUnitFixer + DocBlockAnnotationTrait on a `FlatTokens` window: PHPUnit test classes, attribute names, create / extend a class docblock |
| `src/Preg.php` | upstream `Preg`: a pattern runs with `/u` unless the subject is invalid UTF-8 |
| `src/Rule/*.php` | the rules |
| `GapRule::prepare()` / `preParse()` | per-file candidate test (upstream's `isCandidate`, may precompute) / raw-file pass before lexing (`encoding`) |

## Whole-file rules (P1b)

`braces_position`, `statement_indentation` and `no_extra_blank_lines` are decision walks, not per-gap functions: they declare
`isFileRule()` and the Engine calls `fixFile(Source)` once, in a pass of its own (a file rule never fuses). They still only rewrite
gaps (`setGap`); no token is ever added, removed or moved:

- `no_extra_blank_lines`: the triggers (`use`, `{`, `throw`, ...) only read WHERE line breaks are, which no rewrite changes, so the
  backward walk becomes "mark the whitespace pieces to collapse" + one rewrite per gap (`extra` first, as upstream reaches it first).
- `braces_position`: moving `{` across comments = moving trivia pieces between the gap before `{` and the gap after it. The one move
  that would cross code produces output upstream cannot lint (it then leaves the file untouched): `Source::$abort` does the same.
- `statement_indentation`: upstream's scope stack compares token INDEXES, trivia included (a statement can end on a comment). A
  position is `t << 20 | k` for trivia piece k of gap t and `t << 20 | 0xFFFFF` for token t, so every comparison is an int one.
  Not mirrored: when a token directly after `<?php\n` gets whitespace inserted, upstream's indices drift by one for the rest of the
  file; here they do not (no corpus or case shows a difference).

`Source::join` splices only the rewritten gaps / texts into the original code.

Wave 3 adds more file rules, for rules that write a gap other than their own or decide over the whole file as the earlier rules left it
(`blank_line_after_opening_tag`, `function_declaration`, `class_definition`, `single_line_after_imports`, `array_indentation`,
`no_multiple_statements_per_line`, `blank_line_between_import_groups`) and a flat-copy technique for rules that run upstream's algorithm on a
token list with insertions (`class_definition` copies the header's code tokens and trivia pieces into parallel arrays, runs upstream's code with
the same integer indexes, stale ones included, and stores the pieces back as gaps). Text rules (`fixText`) rewrite the bytes of a token whose
kind and place stay; a rule that needs a token inserted, deleted or moved is `needs edit phase` in `docs/rules-roadmap.md`. A rule whose
decision depends on nothing but kinds and comments (which never change) computes it once in `prepare()` (`switch_case_space`,
`attribute_block_no_spaces`, `single_line_empty_body`, `declare_equal_normalize`).

## Things upstream does that the model has to mirror

- A comment's trailing whitespace belongs to the NEXT whitespace token (`WhitespacyCommentTransformer`).
- `Preg::*` tries `/u` first and falls back to bytes only for invalid UTF-8: under `/u` `\R` also matches U+0085, U+2028/9;
  without it, the lone byte `0x85` (a continuation byte of e.g. a star) — per token.
- A rule that changes one file changes it as a whole: `Tokens::isChanged()` is false when every assignment equals the old token, so `Source::setGap`
  and `Source::setText` ignore an identical gap / text; once something changed, `NAMESPACE\X` comes out as `namespace\X` (the transformer builds that token from a literal).
- A rule that turns a token into one of another KIND (`switch_case_semicolon_to_colon`: `;` -> `:`) changes what every later rule sees
  (`statement_indentation` treats `case X:` differently): `Source::setKind` does it and drops the kind caches (`Structure`, switch colons).
  `GapRule::prepare()` is called right before the pass of its own group, after earlier groups rewrote the file (a file rule moves comments).
- Upstream lints with the tokenizer only (a parse error leaves the file alone; compile errors such as a bad return type are not seen); the
  fixers throw on a missing token at the end of the file (`namespace X; ` + EOF): such a file stays as it is.
- Upstream walks tokens backwards and lints its output; `line_ending` turns U+2028/`\x0b`/`\x0c` into a break and so
  can end a `//` comment — such files are an upstream bug, not a parity target.

## Edit phase (P3)

An edit rule (`EditRule`, a file rule) records span edits with `Source::edit(from, to, text)` over stream units: unit `2i` is gap i,
`2i+1` token i (`Source::gapAt` / `tokAt`). Right after the rule the Engine renders the file and patches the Source (`Source::rebuild`):
only windows around the changed units (from the `;` / `{` / `}` before them to the one after) are lexed again, the other tokens keep
their columns with moved offsets, `Transform` reruns; the next rule sees upstream's token list, custom kinds included
(`docs/design.md`, "Edit phase"; `FMT_PATCH_CHECK=1` checks every patch against a full parse). A rule that records nothing costs its
own scan; an edited file costs one render + patch (about a third of a parse of it).

- Simple rules edit the Source directly (`elseif`, `no_closing_tag`, `array_syntax`, `trailing_comma_in_multiline`, `new_with_parentheses`,
  `no_leading_import_slash`, `single_trait_insert_per_statement`, `single_import_per_statement`); `control_structure_braces` walks with
  VIRTUAL inserts (an outer body's end goes through the `{` / `}` already inserted for an inner one, as upstream's token list does).
- Rules whose upstream algorithm needs integer indexes over an inserted-into list run it on a `FlatTokens` copy (a window, or the whole
  file) and record what changed (`FlatTokens::emit`: one edit per stretch between ANCHORS, original tokens left in their order and
  gap; `ordered_class_elements` anchors the heaviest ascending sequence of its moved runs): `modifier_keywords`,
  `single_class_element_per_statement`, `ordered_imports`,
  `ordered_class_elements` (windows), `nullable_type_declaration`, `ordered_types`, `no_unused_imports` (whole file, stale indexes after an
  insert mirrored).
  Each first decides cheaply on the Source whether anything can change.
- A re-lex merges what upstream keeps apart: two whitespace tokens left adjacent by an edit (`else/* c */ if  (` -> `elseif` + `' '` + `'  '`).
  `Source::wsSplit` records the boundary; `single_space_around_construct` and `no_trailing_whitespace` treat the two parts as upstream does.
- Upstream's lint step is not modelled: an edit whose result upstream rejects is reported only where the construct is known
  (`no_closing_tag` after a closure / match / anonymous class, `control_structure_braces` around an inner `if (..):` with `else`,
  `no_unused_imports` dropping the `;` of a multi-line `use A,\n B;`).
  The risky rules add: `pow_to_exponentiation` with a named argument, `void_return` / `self_accessor` / `final_public_method_for_abstract_class` / `static_private_method` /
  `regular_callable_call` when no `{` / `)` is where upstream's index walk expects it (upstream throws), `psr_autoloading` / `comment_to_phpdoc` when the result is no valid code
  (`Source::abort`: the file stays as it is); `php_unit_expectation` with more than 3 arguments (a LogicException upstream); `phpdoc_to_return_type` on a closure with a `use` list (the type would land in front of the list: upstream's lint rejects the file),
  `phpdoc_to_property_type` for a docblock inside a property list (`private $a, /** @var int */ $b;`: same), a `types_map` target for which the PHP parser ends upstream's process with a compile error (`abstract` / `static` as a parameter type, `private` / `protected` / `public` as a property type).

## Known compiler issues

- `MIR.verify: ownflow: $x read .. past a representation mismatch (arr:arr:int vs mixdead)` for an `array<int, int[]>` local that is built, passed to a function and reset in one loop:
  `tests/repro/ownflow_reset_array_of_int_lists.php` (`ordered_traits` keeps two int lists instead).
- `__McTok` does not find a heredoc / nowdoc end marker after a lone CR: `tests/repro/heredoc_lone_cr_end_marker.php` (the risky dirt corpora avoid a bare CR inside heredocs).
- The prelude's TOKEN_PARSE emulation types `as protected;` (trait alias that only changes visibility) as T_STRING; Zend keeps the
  modifier. `Source::parse` repairs it (workaround, `asFix`).
- `__McTok` (prelude tokenizer) does not stop a `//` comment at a lone `\r`: `tests/repro/lone_cr_line_comment.php`.
- `__McTok` lexes a hex / binary / octal / underscored index of a simple string offset (`"$a[0x1F]"`) as T_NUM_STRING `0` + T_STRING, and leaves `?->` in `"$a?->b"` as string text:
  `tests/repro/string_offset_num_string.php`, `tests/repro/string_nullsafe_interpolation.php` (both show in `explicit_string_variable`; the cases were kept out of `tests/cases`).
- The prelude tokenizer is demand-gated on the name `PhpToken`, not `__McTok`: `Source` carries a dead
  `\PhpToken::class` constant to get it linked.
- `use\C` / `new\X` (a keyword glued to a backslash name) lexes as the keyword + a name, Zend makes one T_NAME_QUALIFIED: issue #363
  (not in tests/cases).
- After `token_get_all()` / `__McTok::run()` of a built string, a lookup in an int-keyed local hash of the same function can answer 0:
  issue #364. `ordered_imports` therefore builds its declaration items without re-lexing (only their bytes and the group `}` matter).
- Int lists vivified in a returned tuple, destructured and passed to an `array<string, int[]>` parameter, read as boxed words: issue #365.
  `no_unused_imports` keeps its occurrence index in a property.
- A `/u` pattern on invalid UTF-8 answers 0 / the subject natively instead of false / null, and `preg_last_error()` is stale: issue #367.
  `Preg` checks the subject once (`//u`) and picks the pattern, which is what upstream's try-and-fall-back amounts to.
- An int list fed from a shape element of a function result (`$k[] = $pair[0]` with `array{0: int, 1: string}`) compares unequal to its own
  values natively: issue #369. `fully_qualified_strict_types` builds its virtual token lists from texts (`nameTexts`), not [kind, text] pairs.
- An `array_splice` of an int list later in a function made an earlier call taking the list (`nextOfKinds($kind, ..)`) read wrong values:
  issue #370; `fully_qualified_strict_types` rebuilds the list by hand.

## PHPDoc rules (the docblock model)

A docblock is trivia: a doc piece of a gap (`Gap::isDoc`: `/**` + whitespace, as the lexer makes a T_DOC_COMMENT). `Gap::flags` sets
`G_DOC` on a gap that holds one, so a phpdoc rule (`DocRule`, needsMask `G_DOC`) is never called for the other gaps; `fixDoc` gets one doc
piece and returns its new text (`''` removes it: upstream's clearTokenAndMergeSurroundingWhitespace leaves exactly the neighbouring
whitespace pieces concatenated). Rules that read the code after the docblock look at token i (the gap's next token is the docblock's next
meaningful token); rules that walk classes are file rules that rewrite doc pieces with `DocRule::mapDocs`.

The name rules port upstream's analyzers over the Source (`Invocation`: isConstantInvocation, isClassyInvocation, isGlobalFunctionUsage,
isAttribute; `Doc\NsScan`: namespace scopes, imports). `fully_qualified_strict_types` runs upstream's walk per namespace (both phases with
import_symbols) and records each shortened name as a span edit over its name tokens; a later visit of the same name reads the replacement,
and the two places where upstream's integer indexes go stale are mirrored: `overrideRange` only clears the tokens a shorter name does not need
(a `catch` then stops after its first shortened type, the variable's walk shortens the rest), and an attribute group's names are visited at
indexes collected before the first change (followed on a virtual copy of upstream's token list).

`src/Doc` ports upstream's `DocBlock\*` (lines split by the same PCRE, annotations sharing the Line objects, Tag, ShortDescription,
TypeExpression with its grammar regexes byte for byte) and is only built for a docblock that passed the rule's text test
(`str_contains($doc, '@access')`, a whole-word scalar name, a non-lowercase type word, `|` / `&` for sorting, ...). Two shortcuts keep a
parse cheap natively: `Line` answers its two predicates (useful content, a tag) without PCRE for an ASCII docblock and caches them, and
`TypeExpression` / `Annotation::getTypesContent` take the grammar's answer for simple names (`int`, `?Foo\Bar`, `string[]`, `int|null`)
without the ~10 us full regex. `php bench/doctypes.php <dirs>` checks the model against upstream's classes under Zend (lines, annotations,
tag names, types content, the inner type tree, variable names). Corpora: `php bench/docsrc.php bench/work/docsrc` (docblock-rich third-party
code: WordPress, doctrine, twig, monolog, upstream's tests ...), `php bench/mutate_doc.php <src> <dst>` (phpdoc dirt), `bench/doc.sh <rules>...`
(the oracle over corpus-src, docsrc, their dirt, dirty2 and tests/cases).

## Gap flags (P0.5)

`Source::gapFlags[i]` classifies gap i once (`Gap::flags`, recomputed by `Source::setGap` on a rewrite). A rule declares
`needsMask()`; the Engine skips it when `flags & mask == 0` (never for the trailing gap or the gap after an open tag).
`G_NL G_CR G_COMMENT G_ODD G_LEADWS_NL G_NL2 G_BLANK_BREAK G_TAB G_DOC` — see `src/Gap.php` (G_TAB is also set on blank-only gaps). Flags 0 = empty or only blanks.
`bench/check.sh` = full parity matrix; `bench/ins3.sh <corpus>` = instructions for 1 and 3 rules.

## Custom tokens and structure (P1a)

`Source::ct[i]` is 0 or a `CT::*` id (same numbers as upstream). `kind[]` never changes and the byte round trip is untouched. The
one exception is upstream's NameQualifiedTransformer: `T_NAME_QUALIFIED/FULLY_QUALIFIED/RELATIVE` are split into
`T_NAMESPACE/T_NS_SEPARATOR/T_STRING` tokens in `Source::parse` (empty gaps between the pieces), because that transformer changes
token boundaries. `Transform` runs after lexing (and after any rebuild, since a rebuild is a `parse`). A rule asks "is token i a
`CT::TYPE_COLON`" with `$s->ct[$i] === CT::TYPE_COLON`; "is it a plain `{`" is `kind === 123 && ct === 0`.

`Structure::build` columns are documented on the class: `owner[]` (what a bracket belongs to: class body, params, args, array,
match, attribute, ...), `parent[]`, `sep[]` (comma chain), statement segments. `Source::isLineStart` / `lineIndent` read the
live gaps, so they stay correct after a rule rewrote a gap.
