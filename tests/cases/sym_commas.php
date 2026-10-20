<?php
use A\{B, C,};
use A\{
    D,
    E,
};
f(1, 2,);
f(1, 2 , );
f(1,2,/* c */);
f(1,2, /* c */ );
f(1,2 /* c */ , );
f(
    1,
);
$a = [1, 2,];
$a = [1, 2 ,];
$a = [1, 2 , /* c */ ];
$a = array(1, 2,);
$a = array(1, 2, );
[$x, $y,] = $z;
list($x, $y,) = $z;
list(, $y,) = $z;
[$a, [$b, $c,],] = $d;
foo(bar(1,),);
foo(bar(1,), [2,],);
$o->method(1,)->other(2,);
new Foo(1,);
new class(1,) {};
$f(1,);
$f(1,)(2,);
isset($a,);
foo(<<<EOT
 x
 EOT,);
foo('multi
line',);
foo(1,2,)
;
#[Attr(1,)]
class L {}
#[Attr(new Foo(1,))]
class M {}
$x = match ($a) { 1 => 2, };
if ($a) {}
while (f(1,)) {}
