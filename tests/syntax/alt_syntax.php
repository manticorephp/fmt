<?php if ($a): ?>
  <b>html</b>
<?php elseif ($b): ?>
  other
<?php else: ?>
  none
<?php endif; ?>
<?php foreach ($xs as $k => $v): ?>
  <?= $v ?>
<?php endforeach ?>
<?php
for ($i = 0; $i < 3; $i++):
    echo $i;
endfor;
while (true):
    break;
endwhile;
switch ($x):
    case 1:
        echo 1;
        break;
    default:
        echo 2;
endswitch;
declare(ticks=1);
declare(strict_types=1);
namespace Foo {
    function a() {}
}
namespace {
    echo 1;
}
echo 'x' ?>
trailing html
<?php
$a = $b ? $c : $d;
do { echo 1; } while (false);
if ($a) { } elseif ($b) { } else { }
try { } catch (\Exception $e) { } finally { }
global $g1, $g2;
static $s1 = 1;
unset($a, $b);
isset($a[1], $b->c) && empty($d);
exit(1);
include 'a.php'; require_once 'b.php';
print 'x';
new class {};
abstract class Z { abstract function f(); }
$f = fn($x) => fn($y) => $x + $y;
throw new Exception();
