#!/bin/bash
# usage: bench/rule.sh <rules-spec>...   -- the oracle on every corpus (clean, dirty..dirty9, docsrc, tests/cases, tests/syntax), all in parallel.
# CORPORA="dirs" limits it. The fmt binary runs under bench/gfmt.sh (kill -9 above 2 GB RSS or 60 s wall); FMT_BIN=<binary> overrides bin/manticore-fmt.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
[ -d bench/work/dirty10 ] || php bench/mutate_risky.php $C bench/work/dirty10 >/dev/null
[ -d bench/work/dirty11 ] || php bench/mutate_risky.php $C bench/work/dirty11 23 untype >/dev/null # type declarations dropped (docblocks carry them): phpdoc_to_{param,return,property}_type
[ -d bench/work/dirty12 ] || php bench/mutate_new.php $C bench/work/dirty12 29 >/dev/null # attributes, heredocs, numeric literals, Doctrine annotations, interface lists, small statements (the last wave of rules)
[ -d bench/work/dirty12d ] || php bench/mutate_new.php bench/work/docsrc bench/work/dirty12d 31 >/dev/null
# upstream's own PHPUnit tests (every 4th *Test.php): PHPUnit classes, data providers, attributes and docblocks for the php_unit_* rules
UT=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/upstream/tests
if [ ! -d bench/work/ut ]; then
  for f in $(find $UT -name '*Test.php' | sort | awk 'NR%4==1'); do rel=${f#$UT/}; mkdir -p bench/work/ut/$(dirname $rel); cp $f bench/work/ut/$rel; done
fi
# ut2: PHPUnit dirt (@internal / @covers dropped, class docblocks emptied); ut3: ut with every data provider moved before its test (upstream's own result)
[ -d bench/work/ut2 ] || php bench/mutate_phpunit.php bench/work/ut bench/work/ut2 >/dev/null
[ -d bench/work/ut4 ] || php bench/mutate_risky.php bench/work/ut bench/work/ut4 >/dev/null # risky PHPUnit dirt (assertSame(true, ..), ->will($this->returnValue(..)), test methods turned into @test)
[ -d bench/work/ut5 ] || php bench/mutate_risky.php bench/work/ut bench/work/ut5 17 dp >/dev/null # PHPUnit data providers renamed / made non-static / return type dropped
if [ ! -d bench/work/ut3 ]; then
  cp -R bench/work/ut bench/work/ut3
  (cd /tmp && php -d xdebug.mode=off -d memory_limit=-1 /Users/taras/var/projects/php-cs-fixer-ds/bench/work/upstream/php-cs-fixer fix --using-cache=no --sequential \
    --rules='{"php_unit_data_provider_method_order":{"placement":"before"}}' "$here/bench/work/ut3" >/dev/null 2>&1)
fi
export REAL_BIN=${FMT_BIN:-$here/bin/manticore-fmt}
export FMT_BIN=$here/bench/gfmt.sh
bad=0
for r in "$@"; do
  logs=()
  for corp in ${CORPORA:-$C bench/work/dirty bench/work/dirty2 bench/work/dirty3 bench/work/dirty4 bench/work/dirty5 bench/work/dirty6 bench/work/docsrc bench/work/dirty6d bench/work/dirty7 bench/work/dirty8 bench/work/dirty9 bench/work/ut bench/work/ut2 bench/work/ut3 bench/work/ut4 bench/work/ut5 bench/work/dirty10 bench/work/dirty11 bench/work/dirty12 bench/work/dirty12d tests/cases tests/syntax}; do
    [ -d "$corp" ] || continue
    log=$(mktemp); logs+=("$log")
    ( out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-400)
      case "$out" in IDENTICAL*) echo "ok   $(basename $corp) ${r:0:80}: $out";; *) echo "FAIL $(basename $corp) ${r:0:80}: $out";; esac ) > "$log" &
  done
  wait
  for log in "${logs[@]}"; do cat "$log"; grep -q '^FAIL' "$log" && bad=1; rm -f "$log"; done
done
exit $bad
