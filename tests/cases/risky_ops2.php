<?php

namespace Foo;

$a = pow($a, 2);
$a = \pow($a, 2,);
$a = pow(2, $b + 1);
$a = pow($a->b, Foo::BAR);
$a = pow(-1, 2);
$a = pow(+1, 2);
$a = pow(pow($a, 2), 3);
$a = pow($a, pow($b, 2));
$a = pow(foo(1, 2), 3);
$a = pow($a, ...$b);
$a = pow(...$b);
$a = pow($a);
$a = pow($a, 2, 3);
$a = pow( /* a */ $a /* b */ , /* c */ 2 /* d */ );
$a = pow($a[1], (int) $b);
$a = pow($$a, ${$b});
$a = pow([1, 2], "x" . "y");
$a = 2 * pow($a, 2) + 3;
$a = pow(2, 3) ** 2;
$a = POW(1.5, 2) . pow(__LINE__, 2) . pow(3, ++$x);
if (strpos($a, $b) === 0) {}
if (strpos($a, $b) !== 0) {}
if (strpos($a, $b) !== false) {}
if (strpos($a, $b) === FALSE) {}
if (0 === strpos($a, $b)) {}
if (false !== strpos($a, $b)) {}
if (\strpos($a, $b) === 0) {}
if (!\strpos($a, $b) === 0) {}
if (strpos($a, $b) === 1) {}
if (strpos($a, $b, 1) === 0) {}
if (strpos($a, $b) === 0 + 1) {}
if (strpos($a, $b) === 0 >= 1) {}
if ($x . strpos($a, $b) === 0) {}
if (-strpos($a, $b) === 0) {}
if (STRPOS($a, $b) !== false && stripos($c, $d) === false) {}
if (\stripos($a, $b) === 0) {}
if (stripos( $a , $b ) !== 0) {}
if (stripos(foo(1, 2), $b[0]) !== false) {}
if (stripos($a,$b) === false) {}
if (false === stripos($a, $b)) {}
