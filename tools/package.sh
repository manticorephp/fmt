#!/usr/bin/env bash
# usage: tools/package.sh <platform> [outdir]  -- dist/manticore-fmt-<VERSION>-<platform>.tar.gz from bin/manticore-fmt
# platform: linux-amd64 | linux-arm64 | linux-musl-amd64 | linux-musl-arm64 | macos-arm64
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
plat="${1:?platform}"; out="${2:-$ROOT/dist}"
ver="$(tr -d '[:space:]' < VERSION)"; name="manticore-fmt-$ver-$plat"
rm -rf "${out:?}/${name:?}"; mkdir -p "$out/$name/bin"
cp bin/manticore-fmt "$out/$name/bin/"; cp LICENSE README.md "$out/$name/"
tar -C "$out" -czf "$out/$name.tar.gz" "$name"; rm -rf "${out:?}/${name:?}"
echo "$out/$name.tar.gz"
