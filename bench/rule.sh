#!/bin/bash
# usage: bench/rule.sh <rules-spec>...   -- the oracle on every corpus (clean, dirty..dirty9, docsrc, tests/cases, tests/syntax), all in parallel.
# CORPORA="dirs" limits it. The fmt binary runs under bench/gfmt.sh (kill -9 above 2 GB RSS or 60 s wall); FMT_BIN=<binary> overrides bin/manticore-fmt.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
export REAL_BIN=${FMT_BIN:-$here/bin/manticore-fmt}
export FMT_BIN=$here/bench/gfmt.sh
bad=0
for r in "$@"; do
  logs=()
  for corp in ${CORPORA:-$C bench/work/dirty bench/work/dirty2 bench/work/dirty3 bench/work/dirty4 bench/work/dirty5 bench/work/dirty6 bench/work/docsrc bench/work/dirty6d bench/work/dirty7 bench/work/dirty8 bench/work/dirty9 tests/cases tests/syntax}; do
    [ -d "$corp" ] || continue
    log=$(mktemp); logs+=("$log")
    ( out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-400)
      case "$out" in IDENTICAL*) echo "ok   $(basename $corp) ${r:0:80}: $out";; *) echo "FAIL $(basename $corp) ${r:0:80}: $out";; esac ) > "$log" &
  done
  wait
  for log in "${logs[@]}"; do cat "$log"; grep -q '^FAIL' "$log" && bad=1; rm -f "$log"; done
done
exit $bad
