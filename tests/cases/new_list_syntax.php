<?php
list($a, $b) = $x;
list ( $a , list($b, $c) ) = $x;
[$a, $b] = $x;
[$a, [$b, $c]] = $x;
foreach ($arr as list($k, $v)) {}
foreach ($arr as [$k, $v]) {}
foreach ($arr as list('a' => $k, 'b' => list($v, $w))) {}
list(, $b) = $x;
list($a[0], $b['k']) = $x;
[$a[0], $b['k']] = $x;
$y = [$a, $b];
$y = $x[0];
$y = $x[1][2];
[$a, [$b, $c[0]]] = [1, [2, 3]];
list(/* c */ $a) = $x;
list /* c */ ($a) = $x;
$z = list ($a) = $x;
fn() => [$a, $b] = $x;
[[$a]] = $x;
