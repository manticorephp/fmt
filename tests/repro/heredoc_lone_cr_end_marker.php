<?php
// the prelude tokenizer (__McTok) does not find a heredoc / nowdoc end marker that follows a lone CR: the whole rest of the file becomes one T_ENCAPSED_AND_WHITESPACE
// Zend:      ..., T_ENCAPSED_AND_WHITESPACE "    foo\r", T_END_HEREDOC "    X", ";" ...
// Manticore: T_ENCAPSED_AND_WHITESPACE "    foo\r    X;\n$b = 1;\n"   (every rule then sees the file as one string; found by the risky PHPUnit rules on bench/mutate_risky.php dirt)
// Related: tests/repro/lone_cr_line_comment.php (a `//` comment is not ended by a lone CR either).
$code = "<?php\n\$a = <<<'X'\n    foo\r    X;\n\$b = 1;\n";
foreach (token_get_all($code) as $t) {
    if (is_array($t)) {
        echo token_name($t[0]), ' ', json_encode($t[1]), "\n";
    } else {
        echo json_encode($t), "\n";
    }
}
