<?php
if (!$a) {}
if ( ! $a) {}
if (!  $a) {}
if (!$a && !$b || !(!$c)) {}
if (!!$a) {}
if (!!!$a) {}
$x = !/* c */$y;
$x = /* c */!$y;
$x = !
$y;
$x = ! // line
$y;
$x = [!$a, !$b];
echo !isset($a), !empty($b);
function f() { return !$this->x; }
$f = fn($v) => !$v;
$z = $a ?: !$b;
$z = $a != $b;
$z = !$a instanceof Foo;
$q = "!$a";
$h = <<<T
!$a {$b}
T;
if (!$a) {
}
?>
<?= !$a ?>
<?php !$a;
