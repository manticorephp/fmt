#!/bin/bash
# usage: bench/build.sh  -- manticore build manticore.json with the pinned dev compiler (MC=<path> overrides); prints only problems
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
${MC:-/Users/taras/var/projects/manticore-ownmix/bin/manticore} build manticore.json 2>&1 | grep -v '^build: application' | head -30
