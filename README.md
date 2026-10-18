# manticore-fmt

A PHP formatter written in PHP, compiled natively by Manticore. php-cs-fixer's rules are the specification: same rule
names, byte-identical output per rule against upstream 3.95.27 under Zend (the oracle). See `docs/design.md`.

Status: **P0** — core + three rules (`no_trailing_whitespace`, `line_ending`, `no_whitespace_in_blank_line`).

## Build

```bash
manticore build manticore.json     # bin/manticore-fmt (the CLI) and bin/fmt-test (tests); ~10 s
```

## Use

```bash
bin/manticore-fmt fix [--dry-run] [--diff] --rules=<csv|@PSR12> <paths...>
```

Exit codes like cs-fixer: 0 ok, 8 would change (`--dry-run`), 16 bad usage or a rule that is not implemented
(`@PSR12` fails naming every missing rule). Changed files are listed as `   N) path`. No config file yet.

## Test

```bash
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src      # 276 files, owned by the fork
bin/fmt-test $C                    # round trip (join(tokens, gaps) == input) on every file, gaps hold only trivia,
                                   # Structure invariants, hand-written rule cases
```

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
| `src/Source.php` | lex once (`__McTok`) into `kind[] start[] end[]` + `gapBefore[]`; token text is a substr |
| `src/Structure.php` | `pair[]`, `depth[]`, statement segments, `lineStart[]`, `blockKind[]` |
| `src/GapRule.php`, `src/Engine.php` | gap rules in cs-fixer priority order; fusion by footprint |
| `src/EditRule.php`, `src/Edit.php` | interface only (rebuild phase is P3) |
| `src/Gap.php` | trivia helpers: cs-fixer's view of a gap, PCRE `\R` semantics |
| `src/Rule/*.php` | the three rules |

## Things upstream does that the model has to mirror

- A comment's trailing whitespace belongs to the NEXT whitespace token (`WhitespacyCommentTransformer`).
- `Preg::*` tries `/u` first and falls back to bytes only for invalid UTF-8: under `/u` `\R` also matches U+0085, U+2028/9;
  without it, the lone byte `0x85` (a continuation byte of e.g. a star) — per token.
- Upstream walks tokens backwards and lints its output; `line_ending` turns U+2028/`\x0b`/`\x0c` into a break and so
  can end a `//` comment — such files are an upstream bug, not a parity target.

## Known compiler issues

- `__McTok` (prelude tokenizer) does not stop a `//` comment at a lone `\r`: `tests/repro/lone_cr_line_comment.php`.
- The prelude tokenizer is demand-gated on the name `PhpToken`, not `__McTok`: `Source` carries a dead
  `\PhpToken::class` constant to get it linked.

## Gap flags (P0.5)

`Source::gapFlags[i]` classifies gap i once (`Gap::flags`, recomputed by `Source::setGap` on a rewrite). A rule declares
`needsMask()`; the Engine skips it when `flags & mask == 0` (never for the trailing gap or the gap after an open tag).
`G_NL G_CR G_COMMENT G_ODD G_LEADWS_NL G_NL2 G_BLANK_BREAK` — see `src/Gap.php`. Flags 0 = empty or only blanks.
`bench/check.sh` = full parity matrix; `bench/ins3.sh <corpus>` = instructions for 1 and 3 rules.
