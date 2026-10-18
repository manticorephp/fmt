<?php
$a = $b?$c:$d;
$a = $b  ?  $c  :  $d;
$a = $b?:$d;
$a = $b ? : $d;
$a = $b ?
    $c :
    $d;
$a = $b
    ? $c
    : $d;
$a = $b ?/* x */$c/* y */:/* z */$d;
$a = $b ? // x
    $c : // y
    $d;
$a = $b ?	$c	:	$d;
$a = $b ? /* x */ : $d;
$a = $b ?/* x */:$d;
$a = $b?$c?1:2:3;
$a = $b?($c?1:2):($e?:4);
switch ($x) {
    case 1:
        break;
    case $y?1:2:
        break;
    default:
        break;
}
if ($x):
    echo 1;
elseif ($y):
    echo 2;
else:
    echo 3;
endif;
for ($i=0;$i<3;$i++):
endfor;
foreach ($a as $b):
endforeach;
while ($x):
endwhile;
declare(ticks=1):
enddeclare;
goto end;
end:
echo $a?'y':'n';
function f(int $a=0, ?int $b=null): ?string { return $a?"x":null; }
f(a: $x?1:2, b: 3);
$m = match (true) { $a => $b?1:2, default => 3 };
enum E: int { case A = 1; }
$f = fn(int $x): int => $x?1:2;
class C { public function g(): static { return $this?->x ?: $this; } }
$a = $b ?? $c ? 1 : 2;
$a = (int)$b?1:2;
$s = "$a ? $b : $c {$a['x']}";
$x = <<<EOT
 a ?b: c
EOT;
