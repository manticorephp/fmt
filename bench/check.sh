#!/bin/bash
# Full parity matrix: every rule alone + combined (two orders) on the clean corpus, the dirtied corpus and tests/cases.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
bin/fmt-test $C | tail -1
bad=0
for corp in $C bench/work/dirty tests/cases; do
  for r in no_trailing_whitespace line_ending no_whitespace_in_blank_line no_trailing_whitespace,line_ending,no_whitespace_in_blank_line line_ending,no_trailing_whitespace,no_whitespace_in_blank_line no_whitespace_in_blank_line,no_trailing_whitespace,line_ending; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp $r 2>&1 | tail -4)
    case "$out" in IDENTICAL*) ;; *) bad=1; echo "FAIL $(basename $corp) $r: $out";; esac
  done
done
[ $bad = 0 ] && echo "oracle: all IDENTICAL" || exit 1
