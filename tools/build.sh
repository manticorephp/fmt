#!/usr/bin/env bash
# usage: tools/build.sh  -- build bin/manticore-fmt and bin/fmt-test.
# Compiler: $MC, else $ROOT/.compiler/bin/manticore (tools/fetch-compiler.sh), else `manticore` on PATH.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
tools/gen-version.sh
if [ -z "${MC:-}" ]; then
  if [ -x .compiler/bin/manticore ]; then MC=.compiler/bin/manticore
  elif command -v manticore >/dev/null 2>&1; then MC=manticore
  else echo "build: no compiler; run tools/fetch-compiler.sh or set MC" >&2; exit 1; fi
fi
want="$(tr -d '[:space:]' < COMPILER_VERSION)"
got="$("$MC" version 2>/dev/null || true)"
[ "$got" = "manticore $want" ] || echo "build: warning: compiler says '${got:-nothing}', COMPILER_VERSION is $want" >&2
"$MC" build manticore.json
