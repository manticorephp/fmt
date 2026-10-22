# Configuration and command line

manticore-fmt is a drop-in for `php-cs-fixer fix` on the rules it implements. A native binary cannot evaluate
`.php-cs-fixer.dist.php`, so the configuration is data: JSON or YAML.

## Config file

`fix` / `check` look for the first of these in the directory of the single path argument, then in the working directory:

```
.manticore-fmt.json  .manticore-fmt.yaml  .manticore-fmt.yml
.manticore-fmt.dist.json  .manticore-fmt.dist.yaml  .manticore-fmt.dist.yml
```

`--config=FILE` names one, `--config=-` ignores every file. Keys are matched without case, `_` and `-`
(`line_ending` == `lineEnding`). Relative paths are relative to the config file.

```yaml
rules:                      # name => true | false | options; "@Set" => true | false (as in cs-fixer)
  '@Symfony': true
  yoda_style: false
  array_syntax: {syntax: short}
risky: false                # allow risky rules (upstream: setRiskyAllowed)
indent: 4                   # a run of spaces, a number of spaces, "\t" or "tab"
lineEnding: "\n"            # "\n" | "\r\n" | "\r" or lf | crlf | cr
paths: [src, tests]         # roots: directories (walked) or files (taken as they are)
exclude: [vendor, fixtures] # directories pruned during the walk, at any depth; a/b only that path; globs allowed. Default: [vendor]
name: ['*.php']             # file name globs (any); default ['*.php']
notName: ['*.blade.php']
path: [src/Foo]             # only files whose relative path matches (glob / /regex/ / plain substring)
notPath: [cache]
ignoreDotFiles: true        # default true; VCS directories are always skipped
cache: {enabled: true, file: .manticore-fmt.cache}
parallel: 8                 # workers; 0 = one per core (default), 1 or false = sequential; {workers: 8}
format: txt                 # txt | json | checkstyle | junit | gitlab
allowUnimplemented: false
pathMode: override          # override | intersection
```

Without `rules` the default is `{"@PSR12": true}`, as upstream's `Config`.

### Finder semantics (upstream's `Finder::create()`)

- `*.php` only, `vendor` pruned at any depth, dot files and dot directories ignored, `.git` `.svn` `.hg` `.bzr` `CVS` `_darcs` always
  skipped, symlinked directories not followed. Explicit file roots are not filtered.
- Paths on the command line replace the config's `paths` and every filter (upstream `--path-mode=override`: a fresh default
  Finder over the given paths). `--path-mode=intersection` keeps the config's files that lie under the given paths.
- No paths anywhere is an error (upstream with the default config does the same). Several paths and no `--config` use the working
  directory's config (upstream refuses).
- Output is sorted by path; paths are shown relative to the working directory.

## Translating a `.php-cs-fixer.dist.php` by hand

| PHP | manticore-fmt |
|---|---|
| `->setRules([...])` | `rules` (same array: `'@Symfony' => true` -> `"@Symfony": true`) |
| `->setRiskyAllowed(true)` | `"risky": true` |
| `->setIndent("\t")` / `->setLineEnding("\r\n")` | `"indent": "\t"` / `"lineEnding": "\r\n"` |
| `Finder::create()->in(__DIR__.'/src')` | `"paths": ["src"]` |
| `->exclude('fixtures')` | `"exclude": ["vendor", "fixtures"]` (the list REPLACES the default, so keep `vendor`) |
| `->name('*.php')` / `->notName(...)` / `->path(...)` / `->notPath(...)` | `name` / `notName` / `path` / `notPath` |
| `->append([...files])`, `->filter(fn)`, `->ignoreDotFiles(false)` | list the files under `paths`; a callback has no JSON form; `"ignoreDotFiles": false` |
| `->setUsingCache(false)` / `->setCacheFile(...)` | `"cache": {"enabled": false}` / `{"file": ...}` |
| `->setParallelConfig(new ParallelConfig(8, 20))` | `"parallel": {"workers": 8, "chunk": 20}` |
| `->registerCustomFixers(...)`, `->setRuleCustomisationPolicy(...)` | not possible: the binary has only its own rules |

`php -d xdebug.mode=off bench/dump_config.php .php-cs-fixer.dist.php > .manticore-fmt.json` does the mechanical part (Zend, with
upstream's vendor; a dev helper, not part of the binary) and lists on stderr what it could not translate.

## Rules

- `--rules=` is cs-fixer's: a csv (`@PSR12,-yoda_style`, `-name` switches a rule or set off) or a JSON object; it replaces the
  config's rules.
- **Sets** come from a table generated from upstream's `RuleSets` (`src/RuleSetTable.php`, regenerate with `bench/gen_tables.php`):
  @PSR1 @PSR2 @PSR12 @PER @PER-CS @PER-CS1.0/2.0/3.0 (also the `1x0` spellings) @Symfony @PhpCsFixer @PHP*Migration @PHPUnit*Migration
  and every `:risky` variant. Resolution is `RuleSet::resolveSet`: nested sets expand in place, a later entry replaces an earlier
  one (options are replaced, not merged), `"@Set": false` switches the whole set off. `bench/presets.php` compares all 96 sets
  with `RuleSet::getRules()` under Zend. `@auto*` sets depend on `composer.json` upstream and are refused: name the set.
- **Aliases**: every upstream deprecated name (`curly_braces_position`, `new_with_braces`, `no_blank_lines_before_namespace`,
  `visibility_required`, ...) and the v2 names upstream 3 rejects (`no_extra_consecutive_blank_lines`, `lowercase_constants`,
  `pre_increment`, ...) map to their successor with upstream's proxy options. A deprecation notice is printed at the end of the run,
  as upstream does.
- **Risky** rules fail the run unless `--allow-risky=yes` / `"risky": true` (upstream's message); with it they run exactly as upstream's (the oracle scripts always pass it). all 76 of upstream's are implemented.
  What depends on the environment upstream runs in is fixed to the oracle's (PHP 8.5.11 with the extensions of `bench/gen_names.php`'s run): `native_function_invocation` `@internal` and `native_constant_invocation`'s built-in constants
  are the oracle's `get_defined_functions()` / `get_defined_constants()` lists (`src/NativeNames.php`, regenerate on a PHP change), `get_class_to_class_keyword` / `phpdoc_readonly_class_comment_to_keyword` / `mb_str_functions`
  assume PHP >= 8.0 / 8.2 / 8.4. `class_keyword` asks upstream's process which classes, interfaces, traits and enums exist: the answer is `NativeNames::CLASS_LIKE` (the oracle PHP's internal ones plus what upstream's
  own composer autoloader can load; a class of the project being formatted is never in it, as upstream, which does not load the project), regenerated with `bench/gen_names.php`. `phpdoc_to_param_type` / `_property_type` / `_return_type`
  ask whether `<?php function f(TYPE $x) {}` (property / return template alike) parses: `TypeSyntax`, generated by `bench/gen_type_syntax.php` from upstream's tokenizer (a type is names joined by `|`, so only the reserved words matter:
  `readonly $x` and `private $x` are valid parameters upstream, `class $x` is not). `psr_autoloading` needs the file: it is skipped for stdin and for names / paths upstream's `supports()` rejects, and `dir` is resolved from the working directory like upstream's.
  **Refused** (a clear error, exit 32; nothing is approximated): `declare_strict_types` option `preserve_existing_declaration` (deprecated: use `strategy`), `final_internal_class` options `annotation_include` / `annotation_exclude`
  (deprecated: use `include` / `exclude`), `random_api_migration` replacements that chain (a replacement that is itself replaced).
- **Not implemented** rules fail the run, naming all of them (exit 16). `--allow-unimplemented` / `"allowUnimplemented": true`
  skips them with one notice line. `manticore-fmt list-rules` shows every upstream rule with its status, `list-sets` the rule
  counts per set, `describe <rule|@set>` the options, defaults and allowed values, `resolve` the effective rules of a run as JSON.
- Unknown names fail with upstream's "did you mean" message (exit 16); a rule with an empty or invalid option set exits 32.

## Command line

```
manticore-fmt fix|check [options] [paths...]
```

`check` is `fix --dry-run`. A path of `-` formats stdin (dry run, reported as `php://stdin`, like upstream).

| option | |
|---|---|
| `--config=FILE` | config file (`-` = none) |
| `--rules=RULES` | csv or JSON, replaces the config's rules |
| `--dry-run`, `-n` | do not write |
| `--diff` | diff of each changed file |
| `--format=txt\|json\|checkstyle\|junit\|gitlab` | report format |
| `--allow-risky[=yes\|no]` | risky rules |
| `--allow-unimplemented` | skip rules without an implementation |
| `--using-cache=yes\|no`, `--no-cache`, `--cache-file=FILE` | content-hash cache |
| `--path-mode=override\|intersection` | how CLI paths combine with the config's finder |
| `--workers=N`, `--sequential` | process pool size (0 = one per core), `--sequential` = 1 |
| `--stop-on-violation` | stop at the first changed / failing file (sequential) |
| `--show-progress=none\|dots` | one character per file on stderr (`.` clean, `F` changed, `S` cached, `I` invalid, `E` error), after the run |
| `-v`, `-q` | `-v`: list the rules that changed each file (needs a rule-by-rule run, slower) and the cache hit count; `-q`: no report |

### Exit codes (upstream's `FixCommandExitStatusCalculator`)

| code | meaning |
|---|---|
| 0 | ok |
| 4 | a file does not lint (dry run only) |
| 8 | files would change (dry run only) |
| 16 | invalid configuration or usage, rules not implemented |
| 32 | invalid rule configuration |
| 64 | a file could not be fixed (the result would not lint, as upstream; or a construct this build refuses) |

Flags are or-ed, as upstream (`12` = 4 + 8).

## Cache

`.manticore-fmt.cache` (or `--cache-file`) maps path -> md5 of the content the file had when it was last known clean. The signature is
md5 of the binary's identity (size + mtime), the resolved rules with options, indent and line ending: any change discards the file.
A file whose content still hashes the same is skipped without being lexed. The cache is on by default only when a config file is
loaded (or with `--using-cache=yes`), so an ad-hoc `--rules=` run leaves no stray file. Like upstream it assumes the rules are
idempotent.

## Parallel

A fork pool: the file list is dealt to N children by size (largest first onto the least loaded child), every child formats its share
and appends one JSON line per file to a private result file, the parent waits for all and prints in path order. The output (and
the files) are the same as a sequential run (`bin/fmt-test` checks it on the corpus); a child that dies leaves its files marked
`worker crashed` (exit 64). Fewer than 12 files run in-process. Progress characters are printed after the merge, not live.

## Known divergences

- `lineEnding: "\r\n"` together with `no_unused_imports`: upstream's fixer tests `str_starts_with($content, "\n")` on whitespace
  that is now `\r\n`, leaving two adjacent whitespace tokens; the gap model merges them, so a file whose removed imports leave
  trailing blanks at EOF can differ in those blanks (found by the oracle on `tests/cases/import_group.php`).
- `--diff` is cs-fixer-like (`--- Original` / `+++ New`, 3 lines of context), not sebastian/diff byte-identical.
- The gitlab report has the first hunk's start line for both `begin` and `end`, and the rule name as description.
- `@auto*` sets, PHP configs, custom fixers: not supported.
