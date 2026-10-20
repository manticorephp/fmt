<?php
if ($a === 1) {}
if ($a == 1) {}
if ($a !== null) {}
if ($a != 'x') {}
if ($a <> true) {}
if (1 === $a) {}
if ($a === $b) {}
if (1 === 2) {}
if ($a->b === 1) {}
if ($a->b->c[1] === 1) {}
if ($a['x'] === [1, 2]) {}
if ($a[0][1] == -1) {}
if ($a === self::FOO) {}
if (self::$a === 1) {}
if (Foo::$a->b === 1) {}
if ($a === Foo::BAR) {}
if (\Foo\Bar::$a === 1) {}
if ($a === foo()) {}
if (foo() === $a) {}
if ($a->foo() === 1) {}
if ($a === (1)) {}
if (($a) === 1) {}
if ($a === 1 && $b === 2) {}
if ($a === 1 || $b !== null or $c == 3) {}
if ($a === 1 ? $b : $c) {}
if ($x = $a === 1) {}
$x = $a === 1;
$x = $a === 1 ?: 2;
$x = $a === 1 + 2;
$x = $a . 'x' === 'y';
$x = ($a === 1);
$x = !($a === 1);
$x = [$a === 1, $b !== null];
$x = foo($a === 1, $b == 2);
$x = $a === 1 && ($b == 2 || $c === 3);
return $a === 1;
echo $a === 1;
$x = $a === ($b === 1);
$x = $a === array(1, 2);
$x = $a === [];
$x = $a === -1.5;
$x = $a === - 1;
$x = $a === 1.5;
$x = $a === "x$y";
$x = $a === true;
$x = $a === TRUE;
$x = $a === PHP_INT_MAX;
$x = $a == $b->c;
$x = [$a] = $b === 1;
$x = $a /* c */ === /* d */ 1;
$x = $a
    === 1;
$x = $a ===
    1;
$x = $a === 1 /* c */;
$x = $a === $b ? 1 : 2;
$x = $a <= 1 || 2 >= $b;
$x = $a < 1;
$x = 1 > $a;
$x = $a ?? 1 === $b;
$x = isset($a) === true;
$x = $$a === 1;
$x = ${'a'} === 1;
$x = $a->{'b'} === 1;
$x = $a->$b === 1;
$x = $a?->b === null;
$x = static::$a === 1;
$x = (int) $a === 1;
$x = new Foo === $a;
$x = $a === new Foo;
$x = clone $a === $b;
$x = fn() => $a === 1;
$x = function () { return $a === 1; };
$x = match ($a === 1) { true => $b === 2, default => $c !== null };
$x = $a === 1 and $b;
foo(a: $a === 1);
switch ($a === 1) { case $b === 2: break; }
while ($a !== null) {}
for ($i = 0; $i !== 3; $i++) {}
?>
<?= $a === 1 ?>
<?php $x = $a === 1 ?>
