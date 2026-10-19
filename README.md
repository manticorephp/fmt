# manticore-fmt

A PHP formatter written in PHP, compiled natively by Manticore. php-cs-fixer's rules are the specification: same rule
names, byte-identical output per rule against upstream 3.95.27 under Zend (the oracle). See `docs/design.md`.

Status: **P1b wave 1** — core, custom tokens (`ct[]`) + structure columns, eight rules: `no_trailing_whitespace`, `line_ending`,
`no_whitespace_in_blank_line`, `indentation_type`, `single_blank_line_at_eof`, `no_extra_blank_lines` (every `tokens` value),
`braces_position` (every option), `statement_indentation` (both options), plus wave 2: `cast_spaces`, `ternary_operator_spaces`, `unary_operator_spaces`,
`method_argument_space`, `binary_operator_spaces` (no align* strategies: refused), `concat_space`, `single_space_around_construct`. `--rules=@PSR12` lists the rest by name.
A file with a T_BAD_CHARACTER is skipped, as upstream skips a file that does not lint.

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

The clean corpus changes nothing under these rules, so it proves little. Use the dirtied corpus and the edge cases too:

```bash
php bench/mutate.php $C bench/work/dirty     # trailing blanks, CRLF/mixed endings, odd \R bytes, bad UTF-8 ... (gitignored)
php -d xdebug.mode=off bench/oracle.php bench/work/dirty no_trailing_whitespace,line_ending,no_whitespace_in_blank_line
php -d xdebug.mode=off bench/oracle.php tests/cases line_ending      # hand-written edge cases (open tag, heredoc, inline HTML, EOF)
```

## Bench

```bash
bench/ins.sh <corpus> <rules>      # instructions retired / wall / RSS, native vs the fork (macOS /usr/bin/time -l)
```

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

## Things upstream does that the model has to mirror

- A comment's trailing whitespace belongs to the NEXT whitespace token (`WhitespacyCommentTransformer`).
- `Preg::*` tries `/u` first and falls back to bytes only for invalid UTF-8: under `/u` `\R` also matches U+0085, U+2028/9;
  without it, the lone byte `0x85` (a continuation byte of e.g. a star) — per token.
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
