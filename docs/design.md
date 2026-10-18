# manticore-fmt — design

A from-scratch PHP formatter compiled by Manticore. php-cs-fixer's rule set is the SPECIFICATION:
same rule names, options, presets (`@PSR12`, `@Symfony`), and **byte-identical output per rule** against
upstream php-cs-fixer 3.95.27 under Zend (the oracle). Goal: CPU within 2–3x of `mago fmt` on `@Symfony`
(fork today: ~8x; 141G instructions on corpus-src, mago 11G).

## Why not the fork
php-cs-fixer = flat `Tokens` list of heap Token objects, 150 fixers each re-walking it, every insert shifts the
tail and drops every cache. The fork got -65% on @PSR12 with local fixes and then flattened out.

## Model
1. **Lex once.** `__McTok::run($src, 0, 1)` (prelude/tokenizer.php; columnar `meta[]`, `texts[]`, `offs[]`,
   Zend `token_get_all`-compatible). Wrap into a `Source`: parallel arrays `kind[i]`, `start[i]`, `end[i]` over the
   original string; token text is `substr`, never stored.
2. **Tokens are immutable. Only the gaps change.** Trivia (T_WHITESPACE, comments) is NOT a token of the model.
   Token i owns `gapBefore[i]` (string: whitespace + comments between token i-1 and i) — the only mutable column.
   Indices never shift, so there is no insertSlices and no cache invalidation.
3. **One structure table per file**, built once after lexing: matching bracket/brace/paren (`pair[i]`), block depth,
   statement start/end, line start flags, "kind of block" (class body, function body, array, match, ...).
   Rules read it; they never re-scan.
4. **Rule kinds.**
   - *Gap rules* (the majority: braces_position, method_argument_space, statement_indentation, line_ending,
     no_trailing_whitespace, ...): a pure function over `(gapBefore[i], context(i))`. Run as a **single priority-ordered
     pipeline per token** (or per token range); order = cs-fixer priority (desc), identical to upstream order.
   - *Edit rules* (add/remove/replace tokens: no_unused_imports, ordered_imports, yoda_style, cast_spaces' sibling
     rules, phpdoc_*): emit span edits against the source; applied in a **rebuild phase** (re-lex + rebuild the table),
     then following gap rules continue. Group consecutive edit rules into one rebuild when their footprints are disjoint.
   - Each rule declares `reads`/`writes` token-kind footprints; the engine fuses only commuting neighbours.
5. **Output** = join tokens and gaps. `--dry-run` / diff / `--format=json` as in cs-fixer.
6. **Parallel:** fork-after-bootstrap pool over files (reuse the design from php-cs-fixer-ds native/ForkPool).
7. **Config:** YAML (and JSON), same keys as `.php-cs-fixer.php` rules array.

## Parity process (the bar for every rule)
`php -d xdebug.mode=off bench/oracle.php <corpus> <rules>` runs upstream under Zend and the native binary on the same
files and requires IDENTICAL output. A rule is "done" only when the oracle is identical on the corpus for
(a) default options and (b) every option value the preset uses. Unsupported rules are listed, never silently skipped:
a preset containing one fails with the rule name.

## Phases
- P0 core (done), P0.5 gap flags (done), P1a custom tokens `ct[]` + structure columns (done; parity with upstream `Tokens::fromCode`: `bench/tokens.sh`). P0 was: Source, gaps, structure table, rule/engine interfaces, driver, oracle harness, 3 trivial rules (end-to-end).
- P1 @PSR12 rules (~45), P2 @Symfony gap rules, P3 edit rules (imports, casts, yoda), P4 phpdoc_*.
- P5 parallel pool, bench vs mago + fork.

## Corpus / oracle
`/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src` (276 files) + `upstream/` (Zend side with its
vendor). Reuse `bench/oracle.php` and `bench/run.php` ideas from php-cs-fixer-ds.
