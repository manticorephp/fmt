# Contributing

## Build

You need the pinned Manticore compiler (`COMPILER_VERSION`) and its toolchain: `clang` (LLVM 15+), a C linker, and the
dev packages of pcre2, openssl and zlib.

```bash
tools/fetch-compiler.sh      # downloads + verifies the pinned release into .compiler/
tools/build.sh               # bin/manticore-fmt + bin/fmt-test (MC=<path> picks another compiler)
```

`tools/build.sh` regenerates `src/Version.php` from `VERSION` (the one source of the version; the compiler version is
`COMPILER_VERSION`). Commit the regenerated file; CI fails on drift.

## Test (no php needed)

```bash
tools/fmt-test.sh                                        # round trip, invariants, hand-written rule cases (tests/cases + tests/syntax)
tools/goldens.sh check                                   # hashes of the formatted trees, goldens/hashes.tsv
```

Every run of the binary should go through `bench/gfmt.sh` (kills it above 2 GB RSS or 60 s); `tools/goldens.sh` does.

### Goldens

`goldens/matrix.tsv` lists (corpus, id, kind, rules); `goldens/hashes.tsv` holds the sha256 of the output tree for each.
Rows of kind `parity` are byte-identical to PHP CS Fixer 3.95.27. Rows of kind `divergence` (corpus `tests/cases-div`)
are KNOWN accepted differences: the hash is our current behaviour, not upstream's. They are:

- `braces` with `position_after_functions_and_oop_constructs: same`: upstream runs its eight fixers on one token list
  without clearing emptied tokens, so a docblock's inner lines are not re-indented.
- `@PHPUnit5x7Migration:risky` / `@PHPUnit11x0Migration:risky`: upstream throws "Missing block end" (stale block cache)
  on a one-line empty `{}` method followed by another and leaves the file alone; we fix it.
- `NAMESPACE\TRUE` with `native_constant_invocation` + `constant_case`: upstream re-lexes `namespaceTRUE` as separate
  tokens; we do not lower-case it.

When behaviour changes on purpose, prove parity first (below), then `tools/goldens.sh write` and commit the diff of
`goldens/hashes.tsv` with the reason.

## Oracle: optional dev tooling (needs php)

The oracle is PHP CS Fixer 3.95.27 under Zend. CI never runs it. You need php 8.5 and a checkout of PHP CS Fixer
3.95.27 with its `vendor/`:

```bash
export UPSTREAM_DIR=/path/to/php-cs-fixer-3.95.27    # default bench/work/upstream
export CORPUS_DIR=/path/to/corpus-src                # default bench/work/corpus-src (276 files)
php -d xdebug.mode=off bench/oracle.php tests/cases @Symfony    # IDENTICAL: N changed files
CORPORA=tests/syntax bash bench/check.sh                         # every rule alone + combinations on one corpus
```

xdebug poisons the oracle (`-d xdebug.mode=off`). Do not rebuild while a matrix runs (`FMT_BIN=<copy of the binary>`).
The generated tables (`RuleSetTable`, `FixerTable`, `NativeNames`, `TypeSyntax`) are committed; regenerate them only on a
PHP CS Fixer bump with `bench/gen_tables.php`, `bench/gen_names.php`, `bench/gen_type_syntax.php`.
`docs/development.md` describes every dev script.

### Large corpora

The full matrix (about 17 corpora, about 115 MB under `bench/work/`) is not committed. Produce a release asset:

```bash
tools/corpora.sh pack 0.1.0      # corpora-0.1.0.tar.zst + corpora-0.1.0.tar.zst.sha256, from bench/work
```

Attach both to the GitHub release `v0.1.0`; the nightly workflow downloads the asset of the latest release, verifies it
and runs `tools/corpora.sh check`, which compares the output hashes in the asset's `hashes.tsv` (same format as
`goldens/hashes.tsv`). The job is skipped when no asset exists.

## Commits

Imperative, semantic, one line of what and why. No generated co-author trailers.

## Release

Edit `VERSION`, run `tools/build.sh`, commit both; `git tag vX.Y.Z && git push origin vX.Y.Z`. `release.yml` builds the
platform binaries and creates the GitHub Release.
