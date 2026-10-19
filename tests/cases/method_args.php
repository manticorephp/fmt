<?php

foo($a ,$b,  $c,
    $d , <<<EOT
x
EOT
 , $e,/* c */$f , // t
    $g
);
bar(
    $x,$y , $z);
function f(#[A] int $a,#[B]  int $b,
  $c = [1 ,2,
    3]) {}
list($a ,  ,$b) = $c;
$f = function ($a ,$b) use ($c ,$d) { return foo($a ,$b); };
$g = fn($a ,$b) => baz( $a,
$b);
new class($a ,$b) {};
echo foo(1 ,2) , bar( 3 ,4 );
$x = [foo(1 ,2) , 'k' =>bar(
 3,4)];
