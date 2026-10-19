#!/bin/bash
# Full parity matrix: every rule alone (each option set a preset uses) + combinations, on the clean corpus, the dirtied
# corpus and tests/cases.
here=$(cd "$(dirname "$0")/.." && pwd); cd "$here"
C=/Users/taras/var/projects/php-cs-fixer-ds/bench/work/corpus-src
[ -d bench/work/dirty ] || php bench/mutate.php $C bench/work/dirty >/dev/null
[ -d bench/work/dirty2 ] || php bench/mutate_space.php $C bench/work/dirty2 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
[ -d bench/work/dirty3 ] || php -d display_errors=0 bench/mutate_case.php $C bench/work/dirty3 >/dev/null
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
  method_argument_space '{"method_argument_space":{"after_heredoc":false,"attribute_placement":"ignore","on_multiline":"ensure_fully_multiline"}}' '{"method_argument_space":{"after_heredoc":true,"on_multiline":"ignore"}}'
  binary_operator_spaces '{"binary_operator_spaces":{"default":"at_least_single_space"}}' '{"binary_operator_spaces":{"default":null,"operators":{"=":"no_space","|":"no_space","**":"no_space","=>":"at_least_single_space"}}}'
  concat_space '{"concat_space":{"spacing":"one"}}'
  single_space_around_construct
  # wave 3: text rules, gap rules, whole-file rules (each option set a preset uses, plus the others the rule has)
  lowercase_keywords lowercase_cast short_scalar_cast lowercase_static_reference constant_case '{"constant_case":{"case":"upper"}}' switch_case_semicolon_to_colon encoding full_opening_tag
  no_space_around_double_colon no_spaces_after_function_name spaces_inside_parentheses declare_equal_normalize
  return_type_declaration '{"return_type_declaration":{"space_before":"one"}}' compact_nullable_type_declaration types_spaces '{"types_spaces":{"space":"single"}}'
  attribute_block_no_spaces switch_case_space single_line_empty_body no_trailing_whitespace_in_comment control_structure_continuation_position
  no_blank_lines_after_class_opening blank_line_after_opening_tag blank_lines_before_namespace '{"blank_lines_before_namespace":{"min_line_breaks":0,"max_line_breaks":1}}' blank_line_after_namespace
  function_declaration '{"function_declaration":{"closure_fn_spacing":"none","closure_function_spacing":"none"}}' class_definition
  '{"class_definition":{"inline_constructor_arguments":false,"space_before_parenthesis":true}}' '{"class_definition":{"single_line":true}}'
  '{"class_definition":{"single_item_single_line":true,"multi_line_extends_each_single_line":true}}'
  single_line_after_imports array_indentation no_multiple_statements_per_line blank_line_between_import_groups
  "$PSR12_NOW" "$SYM_NOW"
  "$(php -d xdebug.mode=off bench/now.php @PSR12)" "$(php -d xdebug.mode=off bench/now.php @PER-CS2.0)" "$(php -d xdebug.mode=off bench/now.php @PER-CS3.0)"
)
# one background job per corpus (the oracle copies the corpus to its own temp dir, so they do not collide)
logs=()
for corp in $C bench/work/dirty bench/work/dirty2 bench/work/dirty3 tests/cases; do
  log=$(mktemp)
  logs+=("$log")
  (
    for r in "${rulesets[@]}"; do
      out=$(php -d xdebug.mode=off bench/oracle.php $corp "$r" 2>&1 | tail -4 | cut -c1-300)
      case "$out" in IDENTICAL*) ;; *) echo "FAIL $(basename $corp) ${r:0:100}: $out";; esac
    done
  ) > "$log" 2>&1 &
done
wait
bad=0
for log in "${logs[@]}"; do
  [ -s "$log" ] && { bad=1; cat "$log"; }
  rm -f "$log"
done
[ $bad = 0 ] && echo "oracle: all IDENTICAL" || exit 1
