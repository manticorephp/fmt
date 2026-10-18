<?php
	if ($a):
		echo 1;
	elseif ($b):
  echo 2;
	else:
	    echo 3;
	endif;
foreach ($xs as $x):
  foreach ($x as $y) echo $y;
endforeach;
while ($i--)
    $i++;
for ($i = 0; $i < 3; $i++)
{
  continue;

}
do {
    $x++;
}
while ($x < 10);
do $x++; while ($x < 20);
switch ($v) {

    case 1:

        echo 1;

        break;
  case 2:
    case 3: {
        echo 3;
    }
    default:
        throw new \Exception('x');

}
switch ($v):
    case 1:
        throw new E();

    default:
        echo 2;
endswitch;
try {
    f();
} catch (A|B $e) {

    throw $e;

} finally
{
    g();
}
declare(ticks=1) {
    h();
}
if ($a) {
    // comment only
}
if ($a) {
    a();
    // stick to else?
} else {
    b();
}
if ($a)  // trailing comment
{
    c();
}
if ($a) { /* block */
    d();
}
label:
goto label;
$r = match (true) {
    $a > 1, $a < 0 => 'x',
    default => 'y',
};
echo $a ? $b : $c;
$x = [

    1,
    2,

];
$y = array(
    'a' => [1,
        2],

);
f(
    $a,

    $b
);
