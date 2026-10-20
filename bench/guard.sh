#!/bin/bash
# usage: bench/guard.sh <seconds> <cmd...>  -- run a command, kill it when it runs longer than <seconds> or its RSS passes 2 GB
secs=$1; shift
"$@" & pid=$!
t=0
while kill -0 $pid 2>/dev/null; do
  sleep 1; t=$((t+1))
  rss=$(ps -o rss= -p $pid 2>/dev/null | tr -d ' ')
  if [ "${rss:-0}" -gt 2000000 ] || [ $t -ge "$secs" ]; then echo "GUARD: killed (rss=${rss}KB t=${t}s)" >&2; kill -9 $pid 2>/dev/null; wait $pid 2>/dev/null; exit 137; fi
done
wait $pid
