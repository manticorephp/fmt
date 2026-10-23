#!/bin/bash
# Token parity vs upstream Tokens::fromCode (kinds + CT ids + boundaries) on the corpus and tests/syntax.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=${CORPUS_DIR:-$here/bench/work/corpus-src}
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
bad=0
for d in tests/syntax $C; do
  php -d xdebug.mode=off bench/dump_tokens.php $d > "$tmp/z.txt" 2>"$tmp/z.err"
  bin/manticore-fmt dump-tokens $d > "$tmp/n.txt"
  if /usr/bin/diff -q "$tmp/z.txt" "$tmp/n.txt" >/dev/null; then echo "tokens IDENTICAL: $(basename $d) ($(grep -c '^== ' "$tmp/n.txt") files)"; else bad=1; echo "tokens DIFFER: $d"; /usr/bin/diff "$tmp/z.txt" "$tmp/n.txt" | head -20; fi
done
exit $bad
