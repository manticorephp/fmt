<?php
// __McTok (prelude tokenizer) does not end a `//` or `#` comment at a lone CR the way Zend's token_get_all does.
// Run: php -r 'foreach (token_get_all(file_get_contents("tests/repro/lone_cr_line_comment.php")) as $t) ...' vs the compiled token_get_all.
// Generate the input: printf '<?php\r// a\r$x = 1;\r' > in.php
// Zend:   T_OPEN_TAG"<?php\r" T_COMMENT"// a" T_WHITESPACE"\r" T_VARIABLE"$x" ...
// Manticore: T_OPEN_TAG"<?php\r" T_COMMENT"// a\r$x = 1;\r"      (the whole rest of the file is one comment)
// Cause: prelude/tokenizer.php scanLineComment stops only at "\n" (and "?>"), Zend also stops at "\r".
foreach (token_get_all("<?php\r// a\r\$x = 1;\r") as $t) {
    echo is_array($t) ? token_name($t[0]) . json_encode($t[1]) : json_encode($t), ' ';
}
echo "\n";
