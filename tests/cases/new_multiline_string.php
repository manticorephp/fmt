<?php
$a = 'single
line';
$b = "double
line";
$c = "no newline";
$d = 'it\'s
a \\ test';
$e = "say \"hi\"
and \\ back";
$f = "with $var
and {$obj->prop}";
$g = b'binary
string';
$h = "interp $x only";
$i = "text
EOD
more";
$j = 'text
 EOD';
$k = "one\ntwo";
$l = "a
b" . 'c
d';
$m = ["x
y" => 'z
w'];
$n = "\$escaped
$x";
$o = <<<EOT
already
heredoc
EOT;
$p = "trailing newline
";
$q = "$a
$b";
$r = B"capital
b";
$s = 'only \n escapes
here \x';
echo "echo
this";
