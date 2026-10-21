<?php

namespace Foo;

$a = $a + 1;
$a = $a - $b;
$a = $a * 2 * 3;
$a = 2 * $a;
$a = 2 + $a;
$a = $a . 'x' . 'y';
$a = $a % 3;
$a = $a | $b;
$a = 1 | $a;
$a = $a ^ $b;
$a = $a & $b;
$a = $a->b + 1;
$a->b = $a->b + 1;
$a->b['c'] = $a->b['c'] . 'x';
$a[1] = $a[1] + 2;
$a[foo()] = $a[foo()] + 2;
Foo::$a = Foo::$a + 1;
$a = $a + 1 + 2;
$a = $a + ($b ? 1 : 2);
$a = $a + 1 ?: 2;
$a = $a instanceof Foo;
$a = ($a) + 1;
$a = $a   +   1;
$a = $a /* c */ + /* d */ 1;
$$a = $$a + 1;
$a = $a + foo($a);
$a = $a * $a * 2;
$a = &$a + 1;
if ($x) $a = $a + 1;
for ($i = 0; $i < 3; $i = $i + 1) {}
foo($a = $a + 1, $b = $b . 'x');
return $a = $a + 1;
switch ($x) { case 1: $a = $a + 1; break; default: $a = $a - 1; }
if ($x): $a = $a + 1; endif;
$a = $a + 1 ?>
<?php $a = $b + $b;
$a = -$a + 1;
$a = $a + -1;
$a = $a & 1;
$a = $a - $b - $c;
$a = $a - ($b - $c);
$a = $b . $a;
if (strlen($a) === 0) {}
if (0 === strlen($a)) {}
if (strlen($a) !== 0) {}
if (strlen($a) > 0) {}
if (strlen($a) >= 1) {}
if (strlen($a) < 1) {}
if (strlen($a) <= 0) {}
if (strlen($a) <= 1) {}
if (1 <= strlen($a)) {}
if (1 > strlen($a)) {}
if (0 < strlen($a)) {}
if (0 >= strlen($a)) {}
if (0 === \strlen($a->b)) {}
if (strlen((string) $a) === 0) {}
if (strlen($a . $b) === 0) {}
if (strlen($a['x']) > 0 && 0 === strlen($b)) {}
if (!strlen($a) === 0) {}
if (strlen($a) === 0 + 1) {}
if (strlen($a) + 1 === 0) {}
if (1 === strlen($a) * 2) {}
if (strlen($a, $b) === 0) {}
if (strlen( /* c */ $a /* d */ ) === 0) {}
if (strlen(foo($a)) === 0) {}
if (strlen(Foo::BAR) === 0) {}
$x = strlen($a) > 0 ? 1 : 2;
