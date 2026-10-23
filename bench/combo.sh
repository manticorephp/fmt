#!/bin/bash
# usage: bench/combo.sh <rules-spec>...  -- like rule.sh, quiet: prints only FAIL lines and one summary
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=${CORPUS_DIR:-$here/bench/work/corpus-src}
bad=0
for r in "$@"; do
  for corp in $C bench/work/dirty bench/work/dirty2 bench/work/dirty3 tests/cases; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-300)
    case "$out" in IDENTICAL*) ;; *) bad=1; echo "FAIL $(basename $corp) ${r:0:120}: $out";; esac
  done
done
[ $bad = 0 ] && echo "combo: all IDENTICAL" || exit 1
