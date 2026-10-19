#!/bin/bash
# usage: bench/cost.sh <corpus> <rules>...  -- instructions retired (G) of native `fix --dry-run` per rules spec, + the floor (a rule that never applies)
here=$(cd "$(dirname "$0")/.." && pwd); corpus=$1; shift
m() { local t; t=$(mktemp); /usr/bin/time -l "${FMT_BIN:-$here/bin/manticore-fmt}" fix --dry-run --no-cache --workers=1 --rules="$1" "$corpus" >/dev/null 2>"$t"; /usr/bin/awk '/instructions retired/{printf "%.2f", $1/1e9}' "$t"; rm -f "$t"; }
echo "floor $(m encoding) G"
for r in "$@"; do echo "$r $(m "$r") G"; done
