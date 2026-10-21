#!/bin/bash
# usage: bench/batch.sh <outfile> <rules-spec>...  -- bench/rr.sh over several specs, written to <outfile> (for a background run: no sleeping in the caller, poll the file)
out=$1; shift
here=$(cd "$(dirname "$0")/.." && pwd)
: > "$out"
for r in "$@"; do bash "$here/bench/rr.sh" "$r" >> "$out" 2>&1; done
echo DONE >> "$out"
