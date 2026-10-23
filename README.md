# manticore-fmt

A fast PHP code formatter, written in PHP and compiled to a native binary by the
[Manticore](https://github.com/manticorephp/compiler) compiler. No PHP runtime is needed to run it.

**Compatibility.** The rules, rule sets, options, configuration semantics and exit codes are compatible with
[PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) 3.95.27: same rule names, same `@PSR12` / `@PER-CS3.0` /
`@Symfony` / `@PhpCsFixer` / `@PHPUnit*Migration:risky` sets, byte-identical output. The implementation is independent
(a new tokenizer, engine and rule code, no PHP CS Fixer code at run time; many rule bodies port upstream algorithms, MIT, see `LICENSE`). All 300 upstream rules are
implemented. A few known divergences are listed in `docs/config.md` and pinned in `goldens/`.

```
$ manticore-fmt version
Manticore Fmt 0.1.0 (rules of PHP CS Fixer 3.95.27)
```

## Install

```bash
curl -fsSL https://github.com/manticorephp/fmt/releases/latest/download/install.sh | bash
export PATH="$HOME/.manticore-fmt/bin:$PATH"
```

`install.sh` downloads the binary for your platform (linux glibc / musl, macOS; amd64 / arm64), verifies it against the
release's `SHA256SUMS` and installs it into `$MANTICORE_FMT_HOME` (default `~/.manticore-fmt`). `MANTICORE_FMT_VERSION=0.1.0`
pins a release. No compiler is needed. The binary links the system `libpcre2-8`, `libssl` / `libcrypto` and `libz`
(Debian: `libpcre2-8-0 libssl3 zlib1g`; Alpine: `pcre2 libssl3 zlib`; macOS: `brew install pcre2 openssl@3`); `install.sh`
runs `manticore-fmt version` after installing and names what is missing.

To build from source see `CONTRIBUTING.md`.

## Use

```bash
manticore-fmt fix   [--config=FILE] [--rules=<csv|@Set|JSON>] [--diff] [--format=txt|json|checkstyle|junit|gitlab] [paths...]
manticore-fmt check [same options]          # like fix --dry-run: exit 8 when a file would change
manticore-fmt list-rules | list-sets | describe <rule|@Set> | resolve | version | help
```

```bash
manticore-fmt fix src tests                       # default rules: @PSR12
manticore-fmt check --rules=@Symfony --diff src
manticore-fmt fix --rules='{"@PER-CS3.0":true,"array_syntax":{"syntax":"short"}}' src
manticore-fmt fix --allow-risky=yes --rules=@PhpCsFixer:risky src
```

On a terminal a live progress line shows on stderr (`--show-progress`), colors follow `--color` / `NO_COLOR` / `FORCE_COLOR`, and `--stats`
prints counts, timing, memory, throughput, the rules that changed most files and the slowest files (`stats` object with `--format=json`).
See `docs/config.md`.

Exit codes are PHP CS Fixer's bitmask (0 / 4 / 8 / 16 / 32 / 64). A content-hash cache and a fork pool (one worker per
core) are on by default.

## Configuration

A native binary cannot evaluate `.php-cs-fixer.dist.php`, so the configuration is data. `.manticore-fmt.{json,yaml,yml}`
(and `.dist`) in the project directory, or `--config=FILE`:

```yaml
rules:
  '@Symfony': true
  yoda_style: false
  array_syntax: {syntax: short}
risky: false
indent: 4              # spaces, or "\t"
lineEnding: "\n"
paths: [src, tests]
exclude: [vendor, fixtures]
```

Rule and set names are PHP CS Fixer's (and its deprecated aliases). The translation of a PHP config, every key, the CLI
options and the divergences: [`docs/config.md`](docs/config.md).

## Speed

276 PHP files, 8.7 MB, 10 cores, `check` mode, vs mago 1.55.0 (wall time):

| tool / rule set          | 1 thread | 10 threads |
|--------------------------|---------:|-----------:|
| mago 1.55.0 (`fmt`)      |   0.45 s |     0.11 s |
| manticore-fmt `@PSR12`       | 0.65 s | 0.10 s |
| manticore-fmt `@PER-CS3.0`   | 0.70 s | 0.12 s |
| manticore-fmt `@Symfony`     | 2.16 s | 0.34 s |
| manticore-fmt `@PhpCsFixer`  | 3.02 s | 0.43 s |

The rule sets differ: mago applies one fixed style, while `@Symfony` / `@PhpCsFixer` run 150-170 rules (docblocks,
imports, ordering) that a pure layout formatter does not have. Compare mago with `@PSR12` / `@PER-CS3.0` and read the
other rows as the price of the larger rule sets. `bench/compare.sh` reproduces the table on your own corpus.

## Tests

```bash
tools/build.sh                 # bin/manticore-fmt + bin/fmt-test
tools/fmt-test.sh              # round trip + structure invariants + hand-written rule cases
tools/goldens.sh check         # output-tree hashes for (rule set, corpus), no php needed
```

`goldens/` holds the sha256 of the formatted tree per (rule set, corpus); they were produced by this binary after
`bench/oracle.php` showed it byte-identical to PHP CS Fixer 3.95.27 on those corpora. See `CONTRIBUTING.md`.

## License

MIT, see `LICENSE`. PHP CS Fixer is MIT licensed by Fabien Potencier and Dariusz Rumiński; this project is not affiliated
with it.
