<?php
$a = <<<EOT
plain text
EOT;
$b = <<<"EOT"
plain text
EOT;
$c = <<<EOT
with $var
EOT;
$d = <<<EOT
escaped \$dollar and \\ backslash
EOT;
$e = <<<EOT
\n newline escape
EOT;
$f = <<<EOT
EOT;
$g = <<<'EOT'
already
EOT;
$h = <<<EOT
\\ \$ \\\$ \\\\
EOT;
$i = <<<EOT
\x41 \101 \u{41} \\x
EOT;
$j = <<<EOT
trailing backslash \\
EOT;
$k = b<<<EOT
binary
EOT;
$l = <<<  EOT
spaces
EOT;
$m = foo(<<<A
one
A, <<<B
two \$ three
B);
$n = <<<EOT
a \ b
EOT;
$o = <<<EOT
{$a} \$b
EOT;
$p = <<<"EOT"
\$x
EOT;
$q = <<<EOT
  indented \$x
  EOT;
