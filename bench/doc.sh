#!/bin/bash
# usage: bench/doc.sh <rules-spec>...  -- the oracle for phpdoc rules: clean corpus, the docblock corpus (bench/docsrc.php), its and the
# clean corpus' phpdoc dirt (bench/mutate_doc.php), the space dirt and tests/cases; prints FAIL lines and one summary (parallel per corpus)
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=${CORPUS_DIR:-$here/bench/work/corpus-src}
[ -d bench/work/docsrc ] || php bench/docsrc.php bench/work/docsrc >/dev/null
[ -d bench/work/dirty6d ] || php bench/mutate_doc.php bench/work/docsrc bench/work/dirty6d >/dev/null
[ -d bench/work/dirty7 ] || php bench/mutate_doc.php $C bench/work/dirty7 >/dev/null
[ -d bench/work/dirty8 ] || php bench/mutate_imports.php bench/work/docsrc bench/work/dirty8 >/dev/null
[ -d bench/work/dirty9 ] || php bench/mutate_imports.php $C bench/work/dirty9 >/dev/null
logs=()
for corp in $C bench/work/docsrc bench/work/dirty6d bench/work/dirty7 ${IMPORTS:+bench/work/dirty8 bench/work/dirty9} bench/work/dirty2 tests/cases; do
  log=$(mktemp); logs+=("$log")
  (
    for r in "$@"; do
      out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-400)
      case "$out" in *"zend=1 "*) out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-400);; esac  # a flaky upstream exit under load: once more
      case "$out" in IDENTICAL*) echo "ok   $(basename $corp) ${r:0:60}: $out";; *) echo "FAIL $(basename $corp) ${r:0:120}: $out";; esac
    done
  ) > "$log" 2>&1 &
done
wait
bad=0
for log in "${logs[@]}"; do
  /usr/bin/grep -q '^FAIL' "$log" && bad=1
  if [ -n "$VERBOSE" ]; then cat "$log"; else /usr/bin/grep -A3 '^FAIL' "$log"; fi
  rm -f "$log"
done
[ $bad = 0 ] && echo "doc: all IDENTICAL" || exit 1
