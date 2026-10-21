#!/bin/bash
# usage: bench/rr.sh <rules-spec>...  -- bench/rule.sh condensed: one line per spec (corpora ok / failed, changed files per corpus that changed any) + the FAIL lines
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
bad=0
for r in "$@"; do
  out=$(bash bench/rule.sh "$r" 2>&1 | cut -c1-400); rc=$?
  ok=$(echo "$out" | /usr/bin/grep -c '^ok')
  fail=$(echo "$out" | /usr/bin/grep -c '^FAIL')
  fired=$(echo "$out" | /usr/bin/awk '/^ok/ && $NF != "files" {} /^ok/ {n=$(NF-2); if (n+0>0) printf "%s=%s ", $2, n}')
  echo "${r:0:70}: ok=$ok fail=$fail changed: $fired"
  echo "$out" | /usr/bin/grep '^FAIL' | head -3
  [ "$fail" -gt 0 ] && bad=1
done
exit $bad
