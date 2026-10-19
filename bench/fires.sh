#!/bin/bash
# usage: bench/fires.sh <rules-spec>...  -- native `fix --dry-run`: how many files each rule changes per corpus (does the dirt make the rule fire?)
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
for r in "$@"; do
  line="$r:"
  for corp in $C bench/work/dirty bench/work/dirty2 bench/work/dirty3 bench/work/dirty6 tests/cases; do
    n=$(bin/manticore-fmt fix --dry-run --no-cache --workers=1 --rules="$r" $corp 2>&1 | /usr/bin/awk '/^Found/{print $2} /^Checked all/{print 0}' | head -1)
    line="$line $(basename $corp)=${n:-?}"
  done
  echo "$line"
done
