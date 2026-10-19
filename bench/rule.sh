#!/bin/bash
# usage: bench/rule.sh <rules-spec>...   -- the oracle on the clean corpus, the dirtied corpora (mutate.php, mutate_space.php) and tests/cases
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
bad=0
for r in "$@"; do
  for corp in $C bench/work/dirty bench/work/dirty2 bench/work/dirty3 tests/cases; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4)
    case "$out" in IDENTICAL*) echo "ok   $(basename $corp) $r: $out";; *) bad=1; echo "FAIL $(basename $corp) $r: $out";; esac
  done
done
exit $bad
