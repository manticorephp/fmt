<?php
$a = die;
$b = DIE(1);
$c = Exit;
$d = EXIT(2);
if ($a <> $b) { echo 1; }
if ($a != $b or $a <> $c) { echo 2; }
$s = b'bin';
$t = B'bin2';
$u = b"bin $a";
$v = B"x";
$w = b<<<EOT
  here $a
  EOT;
$x = B<<<'NOW'
now
NOW;
$y = !!$a;
$z = ! !$a;
$q = !/*c*/ ! $a;
$r = ! /* c */ /* d */ !
  $a;
$p = !!!$a;
$o = !!!!$a;
$n = ! ! ! $a || !!$b && !! $c;
if (!!$a && !(!$b)) {}
$m = !!($a);
$l = !!
$a;
$k = [!!$a, !!$b];
