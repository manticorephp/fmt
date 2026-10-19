<?php
// __McTok (prelude tokenizer) lexes `(binary)` as `(` T_STRING `)`; Zend's token_get_all gives T_STRING_CAST "(binary)".
// Zend:      T_STRING_CAST"(binary)" T_VARIABLE"$y"      Manticore: "(" T_STRING"binary" ")" T_VARIABLE"$y"
// Cause: prelude/tokenizer.php __mc_tok_casts() has no 'binary' => 383 entry (int/integer/bool/boolean/float/double/real/string/array/object/unset/void only).
// Effect here: cast_spaces leaves `(binary)$y` unspaced; any rule keyed on a cast token misses it.
// Filed as manticorephp/compiler#358, fixed on branch fix/ownflow-and-tokenizer (one table entry); lowercase_cast / short_scalar_cast parity needs it.
foreach (token_get_all("<?php \$a = (binary)\$y;") as $t) {
    echo is_array($t) ? token_name($t[0]) . json_encode($t[1]) : json_encode($t), ' ';
}
echo "\n";
