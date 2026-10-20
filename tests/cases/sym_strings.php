<?php
$a = "abc";
$b = "a'b";
$c = "a\"b";
$d = "a\$b";
$e = "a\\b";
$f = "a\nb";
$g = "a\\\nb";
$h = "a\\\\b";
$i = "a\\\"b";
$j = "a\tb";
$k = "";
$l = "\"";
$m = "\\";
$n = "\\'";
$o = b"abc";
$p = B"a\nb";
$q = "$a";
$r = "a $b";
$s = 'already';
$t = "a\u{1F600}b";
$u = "a\x41b";
$v = "a\101b";
$w = "a\e[0m";
$x = "multi
line";
$y = "\\\\\"";
$z = "\\\\\n";
$aa = "é";
$bb = "a\{b";
$cc = "a\\{b";
$dd = "\$";
$ee = "'\"'";
$ff = "{$a}";
$gg = <<<EOT
"abc"
EOT;
echo "x", "y";
class C { const A = "a"; public $p = "p"; function f($a = "d") {} }
$hh = `ls`;
$ii = `ls $x -l`;
$jj = `$cmd`;
$ll = `ls "x"`;
$mm = `echo 'a'`;
$nn = `ls {$a->b} ${c} $d[1]`;
$oo = `a` . `b`;
$pp = `$a$b`;
$qq = `a $b`;
$rr = `ls`
;
$ss = "a${b}c";
$tt = "$${d}";
$uu = "\${d}";
$vv = "${e[1]}";
$ww = "${f}${g}";
$xx = <<<EOT
x ${h} y $${i}
EOT;
$yy = "${z}\$";
$o = "x $a->b y $a?->b";
$p = "x $a->b";
$q = "x $a?->b";
