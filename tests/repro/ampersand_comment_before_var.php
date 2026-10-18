<?php
// __McTok (prelude tokenizer) types `& /* c */ $x` as T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG; Zend skips comments too and gives ..._FOLLOWED_BY_VAR_OR_VARARG.
// Zend:      T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG for `&` in each of: `& /* c */ $a`, `& // c\n $b`, `&/**/...$c`
// Manticore: T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG for all three
// Cause: prelude/tokenizer.php ampFollowedByVar() skips only " \t\n\r" after the `&`, not comments.
// Effect: Transform's TypeIntersection pass then marks `array & /* c */ $out` (a by-ref parameter) as an intersection `&`; unary_operator_spaces
// and every rule keyed on the ampersand kind then differ from upstream.
foreach (token_get_all("<?php foo(& /* c */ \$a, & // c\n \$b, &/**/...\$c);") as $t) {
    if (is_array($t) && $t[0] !== T_WHITESPACE && $t[0] !== T_COMMENT) {
        echo token_name($t[0]), ' ';
    }
}
echo "\n";
