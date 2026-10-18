#!/bin/bash
# Full parity matrix: every rule alone (each option set a preset uses) + combinations, on the clean corpus, the dirtied
# corpus and tests/cases.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
bin/fmt-test $C | tail -1
NEBL_SYM='{"no_extra_blank_lines":{"tokens":["attribute","case","continue","curly_brace_block","default","extra","parenthesis_brace_block","square_brace_block","switch","throw","use"]}}'
NEBL_PSR12='{"no_extra_blank_lines":{"tokens":["use"]}}'
NEBL_ALL='{"no_extra_blank_lines":{"tokens":["attribute","break","case","comma","continue","curly_brace_block","default","extra","parenthesis_brace_block","return","square_brace_block","switch","throw","use","use_trait"]}}'
BP_PSR12='{"braces_position":{"allow_single_line_anonymous_functions":false,"allow_single_line_empty_anonymous_classes":true}}'
BP_ALT='{"braces_position":{"control_structures_opening_brace":"next_line_unless_newline_at_signature_end","functions_opening_brace":"same_line","anonymous_functions_opening_brace":"next_line_unless_newline_at_signature_end","classes_opening_brace":"same_line","anonymous_classes_opening_brace":"next_line_unless_newline_at_signature_end","allow_single_line_empty_anonymous_classes":false,"allow_single_line_anonymous_functions":false}}'
P0=no_trailing_whitespace,line_ending,no_whitespace_in_blank_line
# every implemented rule with the options @PSR12 / @Symfony give it
PSR12_NOW='{"no_trailing_whitespace":true,"line_ending":true,"no_whitespace_in_blank_line":true,"indentation_type":true,"single_blank_line_at_eof":true,"no_extra_blank_lines":{"tokens":["use"]},"braces_position":{"allow_single_line_anonymous_functions":false,"allow_single_line_empty_anonymous_classes":true},"statement_indentation":true}'
SYM_NOW='{"no_trailing_whitespace":true,"line_ending":true,"no_whitespace_in_blank_line":true,"indentation_type":true,"single_blank_line_at_eof":true,"no_extra_blank_lines":{"tokens":["attribute","case","continue","curly_brace_block","default","extra","parenthesis_brace_block","square_brace_block","switch","throw","use"]},"braces_position":{"allow_single_line_anonymous_functions":true,"allow_single_line_empty_anonymous_classes":true},"statement_indentation":{"stick_comment_to_next_continuous_control_statement":true}}'
rulesets=(
  no_trailing_whitespace line_ending no_whitespace_in_blank_line
  $P0 line_ending,no_trailing_whitespace,no_whitespace_in_blank_line no_whitespace_in_blank_line,no_trailing_whitespace,line_ending
  indentation_type single_blank_line_at_eof no_extra_blank_lines "$NEBL_SYM" "$NEBL_PSR12" "$NEBL_ALL"
  braces_position "$BP_PSR12" "$BP_ALT"
  statement_indentation '{"statement_indentation":{"stick_comment_to_next_continuous_control_statement":true}}'
  $P0,indentation_type,single_blank_line_at_eof,no_extra_blank_lines,braces_position,statement_indentation
  unary_operator_spaces '{"unary_operator_spaces":{"only_dec_inc":true}}' ternary_operator_spaces cast_spaces '{"cast_spaces":{"space":"none"}}'
  "$PSR12_NOW" "$SYM_NOW"
)
bad=0
for corp in $C bench/work/dirty bench/work/dirty2 tests/cases; do
  for r in "${rulesets[@]}"; do
    out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4)
    case "$out" in IDENTICAL*) ;; *) bad=1; echo "FAIL $(basename $corp) $r: $out";; esac
  done
done
[ $bad = 0 ] && echo "oracle: all IDENTICAL" || exit 1
