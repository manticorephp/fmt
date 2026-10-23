#!/bin/bash
# usage: bench/build.sh  -- tools/build.sh with only the problems printed (MC=<compiler> overrides)
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
"$here/tools/build.sh" 2>&1 | grep -v '^build: application' | head -30
