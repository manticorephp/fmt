<?php
$singleQuoted = 'String with \" and My\Prefix\\';
$doubleQuoted = "Interpret my \n but not my \a";
$hereDoc = <<<HEREDOC
Interpret my \100 but not my \999
HEREDOC;
$a = 'a\\b \\\\c \d \' \\';
$b = "a\\b \\\\c \d \" \$ \x41 \xZ \u{41} \u41 \0 \08 \9 \e \f \v";
$c = b'x\y';
$d = b"x\y";
$e = "x\y $z \y {$a['\q']} $a[0]\w";
$f = <<<EOT
x\y $z \y \$ \\ \q
EOT;
$g = <<<'EOT'
nowdoc \y \\ \'
EOT;
$h = 'it\'s \\ \\\ ok';
$i = "end \\";
$j = "\\ \\\ \\\\";
$k = '\\\\\\ \ \\';
$l = "tab\t \_ \- \. \/";
$m = `echo \y`;
$n = "q\"q \'";
$o = 'x \x';
$p = "$a \y" . '\y' . <<<X
\y
X;
