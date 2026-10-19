# manticore-fmt

A PHP formatter written in PHP, compiled natively by Manticore. php-cs-fixer's rules are the specification: same rule
names, byte-identical output per rule against upstream 3.95.27 under Zend (the oracle). See `docs/design.md`.

Status: **P1b wave 3** — core, custom tokens (`ct[]`) + structure columns, 45 rules (`bin/manticore-fmt list-rules`): @PSR12 39 of 51, @PER-CS2.0 44 of 58, @PER-CS3.0 45 of 62.

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
  `array_indentation`, `no_multiple_statements_per_line`, `blank_line_between_import_groups`.

`--rules=@PSR12` lists the rest by name (all of them insert, delete, move or merge tokens: see `docs/rules-roadmap.md`, "needs edit phase").
A file with a T_BAD_CHARACTER is skipped, as upstream skips a file that does not lint. A file on which upstream throws (a blank at the very end
of the file behind `namespace X;` / `use X;`) is left as it is, with a notice; so is a file on which a rule would need the edit phase
(`function_declaration` deleting a trailing comma of a single-line signature, `single_line_after_imports` on `use ... ?>`): `   ! file: not fixed, ...`.

## Build

```bash
manticore build manticore.json     # bin/manticore-fmt (the CLI) and bin/fmt-test (tests); ~10 s
```

## Use

```bash
bin/manticore-fmt fix [--dry-run] [--diff] --rules=<csv|@PSR12|JSON> <paths...>
```

`--rules` is cs-fixer's: a csv (`-name` drops one), or a JSON object with options, e.g.
`--rules='{"@PSR12": true, "no_extra_blank_lines": {"tokens": ["use", "extra"]}, "line_ending": false}'`. `@PSR12` carries the
preset's options (`braces_position`, `no_extra_blank_lines`). Indent is four spaces, line ending `\n` (no whitespace config yet).

Exit codes like cs-fixer: 0 ok, 8 would change (`--dry-run`), 16 bad usage or a rule that is not implemented
(`@PSR12` fails naming every missing rule). Changed files are listed as `   N) path`. No config file yet.

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
every file byte for byte. Upstream lives in the fork (`bench/work/upstream`); nothing is copied.

The clean corpus changes nothing under these rules, so it proves little. Use the dirtied corpora and the edge cases too (`bench/rule.sh <rules>...` runs all of them, `bench/combo.sh` is the quiet variant;
`FMT_BIN=<copy of the binary>` lets a long run survive a rebuild; `php bench/now.php @PSR12` prints the --rules JSON of the implemented slice of a preset):

```bash
php bench/mutate_space.php $C bench/work/dirty2   # spaces, tabs, newlines, comments between tokens, statements joined on one line
php bench/mutate_case.php $C bench/work/dirty3    # random case of keywords / true / false / null / self / casts, `case x;`, `elseif` -> `else if`
php bench/mutate.php $C bench/work/dirty     # trailing blanks, CRLF/mixed endings, odd \R bytes, bad UTF-8 ... (gitignored)
php -d xdebug.mode=off bench/oracle.php bench/work/dirty no_trailing_whitespace,line_ending,no_whitespace_in_blank_line
php -d xdebug.mode=off bench/oracle.php tests/cases line_ending      # hand-written edge cases (open tag, heredoc, inline HTML, EOF)
```

## Bench

```bash
bench/ins.sh <corpus> <rules>      # instructions retired / wall / RSS, native vs the fork (macOS /usr/bin/time -l)
```

The floor (parse + engine, no rule doing work) is ~2.9 G instructions on corpus-src (a rule whose `prepare()` says no); every wave-3 rule costs
under 0.25 G on top of it (`/usr/bin/time -l bin/manticore-fmt fix --dry-run --rules=<rule> <corpus>`).

## Layout

| | |
|---|---|
| `src/Source.php` | lex once (`__McTok`, TOKEN_PARSE) into `kind[] start[] end[]` + `gapBefore[]`; token text is a substr; live `isLineStart(i)` / `lineIndent(i)` |
| `src/CT.php`, `src/Transform.php` | cs-fixer's custom token kinds as a second column `ct[]` (0 = none) + `pair[]`; port of `Tokenizer\Transformer\*` in upstream order |
| `src/Structure.php` | `pair[] depth[] parent[] owner[] sep[]` (owner kind of every bracket, comma chains) and statement segments |
| `src/GapRule.php`, `src/Engine.php` | gap rules in cs-fixer priority order; fusion by footprint |
| `src/EditRule.php`, `src/Edit.php` | interface only (rebuild phase is P3) |
| `src/Gap.php` | trivia helpers: cs-fixer's view of a gap, PCRE `\R` semantics |
| `src/Analyzer.php` | shared ports of upstream analyzers: isLambda, isAnonymousClass, switch colons, IndentationTrait's line indentation |
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

## Known compiler issues

- The prelude's TOKEN_PARSE emulation types `as protected;` (trait alias that only changes visibility) as T_STRING; Zend keeps the
  modifier. `Source::parse` repairs it (workaround, `asFix`).
- `__McTok` (prelude tokenizer) does not stop a `//` comment at a lone `\r`: `tests/repro/lone_cr_line_comment.php`.
- The prelude tokenizer is demand-gated on the name `PhpToken`, not `__McTok`: `Source` carries a dead
  `\PhpToken::class` constant to get it linked.

## Gap flags (P0.5)

`Source::gapFlags[i]` classifies gap i once (`Gap::flags`, recomputed by `Source::setGap` on a rewrite). A rule declares
`needsMask()`; the Engine skips it when `flags & mask == 0` (never for the trailing gap or the gap after an open tag).
`G_NL G_CR G_COMMENT G_ODD G_LEADWS_NL G_NL2 G_BLANK_BREAK G_TAB` — see `src/Gap.php` (G_TAB is also set on blank-only gaps). Flags 0 = empty or only blanks.
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
