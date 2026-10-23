#!/usr/bin/env bash
# usage: tools/fmt-test.sh [BIN]  -- bin/fmt-test over tests/cases + tests/syntax (a copy, minus tests/fmt-test-skip.txt), under bench/gfmt.sh.
# fmt-test wants a corpus of more than 12 files (its pool check). The skipped files make fmt-test itself SIGSEGV (bin/manticore-fmt
# formats them fine, see goldens/); known open problem, find the cause before shrinking the list.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
export REAL_BIN="${1:-$ROOT/bin/fmt-test}"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
cp -R tests/cases "$tmp/cases"; cp -R tests/syntax "$tmp/syntax"
while IFS= read -r f; do [ -n "$f" ] && rm -f "$tmp/cases/$f" "$tmp/syntax/$f"; done < tests/fmt-test-skip.txt
bench/gfmt.sh "$tmp" | tail -3
