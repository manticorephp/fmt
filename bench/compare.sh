#!/bin/bash
# usage: bench/compare.sh <dir> <rules-json-or-set> [runs=3] [contenders...]
# Wall / user+sys CPU / instructions retired / peak RSS (macOS /usr/bin/time -l), median of N runs, dry run, no cache, from a temp cwd.
# contenders: fmt-seq fmt-par zend native mago   (default: all that exist). Not equivalent work for mago (other style, other rules).
dir=$(cd "$1" && pwd); rules=$2; runs=${3:-3}; shift 3 2>/dev/null
here=$(cd "$(dirname "$0")/.." && pwd)
FORK=${FORK_DIR:-/nonexistent}
want=${*:-fmt-seq fmt-par zend native mago}
fmt=${FMT_BIN:-$here/bin/manticore-fmt}
tmp=$(mktemp -d); cd "$tmp"
n=$(find "$dir" -name '*.php' | wc -l | tr -d ' ')
echo "corpus: $dir ($n files), rules: ${rules:0:60}, load: $(uptime | sed 's/.*averages: //')"
echo "| contender | wall s | cpu s (user+sys) | instructions G | peak RSS MB | exit |"
echo "|---|---|---|---|---|---|"
one() { # name cmd...
  name=$1; shift
  ws=(); cs=(); is=(); rs=(); ex=0
  for i in $(seq $runs); do
    /usr/bin/time -l "$@" >/dev/null 2>t.err; ex=$?
    real=$(awk '/ real /{print $1}' t.err); u=$(awk '/ real /{print $3}' t.err); s=$(awk '/ real /{print $5}' t.err)
    ins=$(awk '/instructions retired/{print $1}' t.err); rss=$(awk '/maximum resident/{print $1}' t.err)
    ws+=($real); cs+=($(echo "$u $s" | awk '{print $1+$2}')); is+=($ins); rs+=($rss)
  done
  med() { printf '%s\n' "$@" | sort -n | awk '{a[NR]=$1} END{print a[int((NR+1)/2)]}'; }
  printf '| %s | %s | %s | %s | %s | %s |\n' "$name" "$(med "${ws[@]}")" "$(med "${cs[@]}")" "$(echo "$(med "${is[@]}") 1000000000" | awk '{printf "%.1f",$1/$2}')" "$(echo "$(med "${rs[@]}") 1048576" | awk '{printf "%.0f",$1/$2}')" "$ex"
}
for c in $want; do
  case $c in
    fmt-seq) one "manticore-fmt sequential" "$fmt" check --no-cache --config=- --workers=1 --rules="$rules" "$dir";;
    fmt-par) one "manticore-fmt parallel ($(sysctl -n hw.ncpu) workers)" "$fmt" check --no-cache --config=- --rules="$rules" "$dir";;
    zend) one "php-cs-fixer (Zend, sequential)" php -d xdebug.mode=off -d memory_limit=-1 ${UPSTREAM_DIR:-$here/bench/work/upstream}/php-cs-fixer fix --dry-run --using-cache=no --sequential --rules="$rules" "$dir";;
    native) [ -x $FORK/bin/php-cs-fixer ] && one "php-cs-fixer native fork (sequential)" $FORK/bin/php-cs-fixer fix --dry-run --using-cache=no --sequential --rules="$rules" "$dir";;
    native-par) [ -x $FORK/bin/php-cs-fixer ] && one "php-cs-fixer native fork (parallel)" $FORK/bin/php-cs-fixer fix --dry-run --using-cache=no --rules="$rules" "$dir";;
    mago) which mago >/dev/null && one "mago format --dry-run (reference)" mago format --dry-run "$dir";;
  esac
done
rm -rf "$tmp"
