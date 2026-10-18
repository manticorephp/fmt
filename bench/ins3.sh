#!/bin/bash
# instructions retired (G) for 1 rule and for the 3 P0 rules on a corpus: marginal cost per extra pass
here=$(cd "$(dirname "$0")/.." && pwd)
for r in no_trailing_whitespace line_ending no_whitespace_in_blank_line no_trailing_whitespace,line_ending,no_whitespace_in_blank_line; do
  /usr/bin/time -l "$here/bin/manticore-fmt" fix --dry-run --rules=$r "$1" 2>&1 >/dev/null | awk -v r=$r '/instructions retired/{printf "%-70s %.2f G\n", r, $1/1e9}'
done
