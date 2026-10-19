<?php
$a = 1; $b = 2;
    $c = 1;   $d = 2;   // c
    $e = 1; /* c */ $f = 2;
    $e = 1; /* c */$f = 2;
    $e = 1;/* c *//* d */$f = 2;
    $g = 1; // c
    $h = 2;
for ($i = 0; $i < 3; $i++) { $x = 1; $y = 2; }
for (;;) { break; }
if ($a) { $x = 1; } else { $y = 2; }
function f() { return 1; }
class C { public $a; public $b; function m() { $a = 1; return $a; } const A = 1; const B = 2; }
switch ($a) { case 1: $x = 1; break; default: break; }
if ($a): $x = 1; endif;
foreach ($a as $b): $x = 1; endforeach;
while ($a): $x = 1; endwhile;
$f = function () { return 1; }; $g = 2;
echo 1; ?>
<?php echo 2; echo 3; ?>
<?php
$x = 1;;
$y = 2; ;
abstract class D { abstract function f(); abstract function g(); }
$o = new class { public $a = 1; public $b = 2; };
class E { public int $x { get { return 1; } set { $this->y = 1; $this->z = 2; } } }
	$t = 1; $u = 2;
$v = 1; $w = 2
?>
