#!/bin/bash
# Full parity matrix: every rule alone (each option set a preset uses) + combinations, on the clean corpus, the dirtied
# corpus and tests/cases.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
bin/fmt-test $C | tail -1
NEBL_SYM='{"no_extra_blank_lines":{"tokens":["attribute","case","continue","curly_brace_block","default","extra","parenthesis_brace_block","square_brace_block","switch","throw","use"]}}'
NEBL_PSR12='{"no_extra_blank_lines":{"tokens":["use"]}}'
NEBL_ALL='{"no_extra_blank_lines":{"tokens":["attribute","break","case","comma","continue","curly_brace_block","default","extra","parenthesis_brace_block","return","square_brace_block","switch","throw","use","use_trait"]}}'
P0=no_trailing_whitespace,line_ending,no_whitespace_in_blank_line
rulesets=(
  no_trailing_whitespace line_ending no_whitespace_in_blank_line
  $P0 line_ending,no_trailing_whitespace,no_whitespace_in_blank_line no_whitespace_in_blank_line,no_trailing_whitespace,line_ending
  indentation_type single_blank_line_at_eof no_extra_blank_lines "$NEBL_SYM" "$NEBL_PSR12" "$NEBL_ALL"
  $P0,indentation_type,single_blank_line_at_eof,no_extra_blank_lines
)
bad=0
for corp in $C bench/work/dirty tests/cases; do
  for r in "${rulesets[@]}"; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4)
    case "$out" in IDENTICAL*) ;; *) bad=1; echo "FAIL $(basename $corp) $r: $out";; esac
  done
done
[ $bad = 0 ] && echo "oracle: all IDENTICAL" || exit 1
