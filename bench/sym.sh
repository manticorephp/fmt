#!/bin/bash
# usage: bench/sym.sh <rules-spec>...  -- the oracle on the clean corpus, dirty..dirty6 (bench/mutate_sym.php, all families) and tests/cases; quiet
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
[ -d bench/work/dirty6 ] || php bench/mutate_sym.php $C bench/work/dirty6 >/dev/null
bad=0
for r in "$@"; do
  for corp in $C bench/work/dirty bench/work/dirty2 bench/work/dirty3 bench/work/dirty6 tests/cases; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-400)
    case "$out" in IDENTICAL*) echo "ok   $(basename $corp) ${r:0:60}: $out";; *) bad=1; echo "FAIL $(basename $corp) ${r:0:100}: $out";; esac
  done
done
exit $bad
