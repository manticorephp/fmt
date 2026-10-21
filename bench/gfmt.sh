#!/bin/bash
# RSS watchdog around the fmt binary ($REAL_BIN): kill -9 above 2 GB RSS or 60 s wall; logs BLOWUP to $GFMT_LOG (default /tmp/gfmt-blowups.log)
# and keeps the last argument (the corpus copy) under $GFMT_KEEP/bN (cap 10).
log=${GFMT_LOG:-/tmp/gfmt-blowups.log}; keep=${GFMT_KEEP:-/tmp/gfmt-blow}
"$REAL_BIN" "$@" & pid=$!
t=0
while kill -0 $pid 2>/dev/null; do
  sleep 0.25; t=$((t+1))
  rss=$(ps -o rss= -p $pid 2>/dev/null | tr -d ' ')
  if [ "${rss:-0}" -gt 2000000 ] || [ $t -ge 240 ]; then
    kill -9 $pid 2>/dev/null; wait $pid 2>/dev/null
    n=$(ls "$keep" 2>/dev/null | wc -l)
    echo "BLOWUP rss=${rss}KB t=$((t/4))s args=$*" >> "$log"
    [ "$n" -lt 10 ] && { mkdir -p "$keep"; cp -R "${@: -1}" "$keep/b$n" 2>/dev/null; }
    exit 137
  fi
done
wait $pid
