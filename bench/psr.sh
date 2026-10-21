#!/bin/bash
# usage: bench/psr.sh  -- psr_autoloading on tests/psr (a path-dependent rule: needs the real file path): default, `dir` = the work directory and a sub directory (ORACLE_SAMEDIR runs both fixers in the same directory)
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
export REAL_BIN=$here/bin/manticore-fmt FMT_BIN=$here/bench/gfmt.sh
php -d xdebug.mode=off bench/oracle.php tests/psr psr_autoloading
for r in '{"psr_autoloading":{"dir":"@WORK@"}}' '{"psr_autoloading":{"dir":"@WORK@/Acme"}}' '{"psr_autoloading":{"dir":"."}}'; do ORACLE_SAMEDIR=1 php -d xdebug.mode=off bench/oracle.php tests/psr "$r"; done
