#!/bin/bash
# usage: bench/ins.sh <corpus> <rules>   -- instructions retired / wall / peak RSS of native vs the native php-cs-fixer fork
# (sequential, no cache, --dry-run), via /usr/bin/time -l (macOS).
corpus=$1; rules=$2
here=$(cd "$(dirname "$0")/.." && pwd)
fork=${FORK_BIN:?set FORK_BIN to a native php-cs-fixer fork binary}
measure() {
  local tmp; tmp=$(mktemp)
  /usr/bin/time -l "$@" >/dev/null 2>"$tmp"
  awk '/instructions retired/{i=$1} /maximum resident/{r=$1} /real/{w=$1} END{printf "%.2f G instr | %.2f s | %.0f MB\n", i/1e9, w, r/1048576}' "$tmp"
  rm -f "$tmp"
}
echo -n "native $rules: "; measure "$here/bin/manticore-fmt" fix --dry-run --rules="$rules" "$corpus"
echo -n "fork   $rules: "; measure "$fork" fix --dry-run --using-cache=no --sequential --rules="$rules" "$corpus"
