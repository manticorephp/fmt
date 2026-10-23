#!/usr/bin/env bash
# Large-corpus asset (not committed: ~115 MB under bench/work/).
#   tools/corpora.sh pack <ver> [BIN]   -- corpora-<ver>.tar.zst (+ .sha256) from bench/work/{dirty*,docsrc,ut*}, with matrix.tsv/hashes.tsv
#                                          computed by BIN. Run bench/check.sh (the oracle) on those corpora FIRST: pack records what
#                                          the binary does, it does not prove parity.
#   tools/corpora.sh check <asset.tar.zst> [BIN]  -- verify the asset's .sha256 (when beside it), extract, rerun every row.
# Needs zstd. No php.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
cmd="${1:?usage: tools/corpora.sh pack <ver> [BIN] | check <asset> [BIN]}"
if command -v sha256sum >/dev/null 2>&1; then sum() { sha256sum "$@"; }; else sum() { shasum -a 256 "$@"; }; fi
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
case "$cmd" in
  pack)
    ver="${2:?version}"; bin="${3:-$ROOT/bin/manticore-fmt}"
    mkdir -p "$tmp/c"
    for d in bench/work/dirty* bench/work/docsrc bench/work/ut*; do [ -d "$d" ] && cp -R "$d" "$tmp/c/$(basename "$d")"; done
    [ -n "$(ls "$tmp/c")" ] || { echo "corpora: nothing under bench/work" >&2; exit 1; }
    { printf '# corpus\tid\tkind\trules\n'
      for c in $(ls "$tmp/c"); do
        awk -F'\t' -v c="$c" '$1=="tests/cases" && $3=="parity" {printf "%s\t%s\tparity\t%s\n", c, $2, $4}' goldens/matrix.tsv
      done; } > "$tmp/c/matrix.tsv"
    GOLDENS_DIR="$tmp/c" CORPUS_ROOT="$tmp/c" tools/goldens.sh write "$bin"
    tar -C "$tmp/c" -cf - . | zstd -19 -T0 -q -o "corpora-$ver.tar.zst"
    sum "corpora-$ver.tar.zst" > "corpora-$ver.tar.zst.sha256"
    echo "corpora: wrote corpora-$ver.tar.zst ($(du -h "corpora-$ver.tar.zst" | cut -f1))" ;;
  check)
    asset="${2:?asset}"; bin="${3:-$ROOT/bin/manticore-fmt}"
    if [ -f "$asset.sha256" ]; then (cd "$(dirname "$asset")" && sum -c "$(basename "$asset").sha256"); fi
    mkdir -p "$tmp/c"; zstd -dc "$asset" | tar -C "$tmp/c" -xf -
    GOLDENS_DIR="$tmp/c" CORPUS_ROOT="$tmp/c" tools/goldens.sh check "$bin" ;;
  *) echo "usage: tools/corpora.sh pack <ver> [BIN] | check <asset> [BIN]" >&2; exit 2 ;;
esac
