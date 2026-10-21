<?php
$a = "My name is $name !";
$b = "I live in $state->country !";
$c = "I have $farm[0] chickens !";
$d = "I have $farm[key] chickens $farm[-1] !";
$e = "$a$b $c[0]$d";
$f = "$a[0]$b[1]";
$g = "$a $b->c $d[e] $f";
$h = "{$a} ${b} {$c['x']} $d";
$i = "$a->b->c $d[0][1]";
$j = b"x $y";
$k = <<<EOT
Hello $name
 and $obj->prop and $arr[0] and $arr[k] x$y
EOT;
$l = <<<"EOT"
$a
EOT;
$m = <<<'EOT'
$a not interpolated
EOT;
$n = `ls $dir $x[1]`;
$o = "a" . $b . "c $d" . $e;
$p = "$a";
$q = "$a$b";
$r = "{$a}$b";
$s = "$a{$b}";
$t = "\$a $b \\$c";
$u = "$a[$b]";
$v = "$a[$b] $c";
$w = "$a[0]$b";
$x = "$a->b$c";
$y = "x$a[0]y";
$z = "${a}b$c";
$z3 = "$a[01]";
$z4 = "$a[ab]";
$z5 = "$a->b[0]";
$z7 = "$$a";
$z8 = "$a $b[$c] $d[$e]$f";
echo <<<EOT
$a
$b
EOT . "x $c";
echo <<<EOT
  $a[0]
    $b->c
  EOT;
