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
   - *Edit rules* (add/remove/replace tokens: no_unused_imports, ordered_imports, yoda_style, phpdoc_*): emit span
     edits against the live stream; applied in a **rebuild** (render + re-lex) right after the rule, then the following
     rules continue on the new Source. See "Edit phase".
   - Each rule declares `reads`/`writes` token-kind footprints; the engine fuses only commuting neighbours.
5. **Output** = join tokens and gaps. `--dry-run` / diff / `--format=json` as in cs-fixer.
6. **Parallel:** fork pool (`Pool`): files dealt to N children by size, one result file per child, merged in path order (done; `docs/config.md`).
7. **Config:** JSON / YAML (`ConfigLoader`), the rules array as in `.php-cs-fixer.php`; sets from a generated copy of upstream's `RuleSets`.

## Edit phase (P3): rules that insert, delete, replace, merge or move tokens
Upstream runs every fixer in one global priority order, each on the previous fixer's output, and edit rules sit everywhere in
it (ordered_class_elements 65 ... ordered_imports -30). So the edit phase is not one batch: an edit rule is a **barrier** in the
Engine's group list, exactly like a file rule, and the gap groups before and after it run on either side.

- **Expression.** An edit rule (`EditRule extends GapRule`, a file rule) reads the live Source (columns + current gaps / texts)
  and records edits with `Source::edit(from, to, text)`: a replacement of whole STREAM UNITS, unit `2i` = gap i, unit `2i+1` =
  token i (`Source::gapAt(i)` / `tokAt(i)`), `[from, to)`, sorted, disjoint. A token text change, a deleted token with its gap, an
  inserted `;` / `()` / `,` are each one span. A rule whose upstream algorithm needs integer indexes over an inserted-into list
  (imports, class elements) runs it on a `FlatTokens` copy; `FlatTokens::emit` records the rendering as one edit per stretch
  between ANCHORS (an original token in its original order, after its untouched gap), so only what changed is an edit; no
  render, and nothing at all when nothing changed. Anchors are taken first come, which re-lexes every run that a sort moved
  in front of a later one; `ordered_class_elements` asks for the heaviest ascending sequence of the runs instead (a Fenwick
  tree over the runs' first indexes), so only the moved elements are re-lexed.
- **Rebuild.** After the rule, if it recorded an edit, the Engine renders the stream (units with the edits spliced, the
  `NAMESPACE\X` -> `namespace\X` rewrite applied as for any changed file) and **patches** the Source (`Source::patch`): each edit
  is shrunk to the units whose bytes change (common prefix / suffix with the old code, whole units), then widened to a window
  between two RESTART POINTS: `;`, `{`, `}`, an open tag, outside every string / heredoc / backtick literal and before any
  `__halt_compiler`. After one the lexer is in plain PHP state, and the TOKEN_PARSE reclassification never looks back across it.
  Gaps / token texts rewritten since the parse are windows too (an emptied gap between `-` and `-` lexes as one `--`). All windows
  are lexed in ONE `__McTok` call (`<?php ` + their texts back to back); every other token keeps its kind and gap, offsets moved
  (in place when each window lexed back to as many tokens as it had); `Transform` reruns over the file (`ct[] pair[]`); Structure
  and switch colons stay lazy. A window that does not lex back to its closing restart point, or windows over more than 3/4 of the
  tokens: the file is parsed whole. Re-lexing the windows is what gives inserted tokens upstream's custom kinds (`[` of a new short
  array, `{` of a new block). `FMT_PATCH_CHECK=1` compares every patched Source with a full parse of its code (exit 70 on a
  difference). Rendered == current text: no rebuild, nothing changed (upstream's `isChanged`). `abort` / `unsupported` carry over;
  the rebuilt Source is marked dirty.
- **Cost.** A rule that is no candidate (`prepare()` false: a kind scan) or finds nothing costs its scan and nothing else: no
  render, no re-lex, no table. A rule that edits a file costs one render + a patch of that file: about 30% of a parse of it
  (moving the columns, Transform), plus the lexing of its windows. Consecutive edit rules each rebuild on their own (they read
  each other's output, as upstream).
- **Mirroring.** A re-lex merges what upstream keeps as separate tokens (two adjacent whitespace tokens after an insert,
  an emptied token); upstream's `clearEmptyTokens` after every fixer removes the latter, the former is a known divergence
  found only by the oracle. Upstream quirks of a fixer (stale indexes after `insertAt`) are mirrored in the rule's own walk.

## Parity process (the bar for every rule)
`php -d xdebug.mode=off bench/oracle.php <corpus> <rules>` runs upstream under Zend and the native binary on the same
files and requires IDENTICAL output. A rule is "done" only when the oracle is identical on the corpus for
(a) default options and (b) every option value the preset uses. Unsupported rules are listed, never silently skipped:
a preset containing one fails with the rule name.

## Phases
- P0 core (done), P0.5 gap flags (done), P1a custom tokens `ct[]` + structure columns (done; parity with upstream `Tokens::fromCode`: `bench/tokens.sh`). P0 was: Source, gaps, structure table, rule/engine interfaces, driver, oracle harness, 3 trivial rules (end-to-end).
- P1b wave 1 (done): indentation_type, single_blank_line_at_eof, no_extra_blank_lines, braces_position, statement_indentation;
  rule options (JSON `--rules`), whole-file rules (`GapRule::isFileRule`/`fixFile`), see README.
- P1b wave 2 (done): cast_spaces, concat_space, ternary / unary / binary operator spaces, method_argument_space, single_space_around_construct.
- P1b wave 3 (done): every remaining @PSR12 / PER-CS2.0 / PER-CS3.0 rule that keeps the token list as it is — text rules (`fixText`: keyword / cast /
  constant case, `;` -> `:`, `<?PHP`), gap rules, file rules (namespace / imports / class / function declarations, array and statement
  layout), a raw-file pre-pass (BOM). `GapRule::prepare()` = upstream's `isCandidate` (+ per-file precompute). What is left needs the edit phase:
  `docs/rules-roadmap.md`.
- P3 edit phase (done for @PSR12 / @PER-CS2.0 / @PER-CS3.0): the 15 edit rules, every preset rule implemented (`docs/rules-roadmap.md`).
- P2 @Symfony gap rules, P3 rest (@Symfony edit rules: imports, casts, yoda), P4 phpdoc_*.
- P5 CLI (done): config files, every upstream set and alias, upstream options + exit codes, reports, cache, fork pool (`docs/config.md`); `bench/compare.sh` measures against Zend, the fork and mago.

## Corpus / oracle
`bench/work/corpus-src` (276 files, not committed: `CORPUS_DIR`) + a php-cs-fixer 3.95.27 checkout with its vendor (`UPSTREAM_DIR`), the Zend side
of the oracle. See CONTRIBUTING.md. `bench/oracle.php` is the comparison.
