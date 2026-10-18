<?php
$a ++;
$a   --;
-- $a;
++   $a;
$a[1] ++;
$a['x']   --;
$a->b ++;
$a->b->c   --;
$a::$b ++;
$$a ++;
${$a} ++;
$a->{$b} ++;
$a = ! $b;
$a = ! ! $b;
$a = ~  $c;
$a = @ $b;
$a = - $b;
$a = + $b;
$a = $b - $c;
$a = $b + $c;
$a = $b - - $c;
$a = $b - -$c;
$a = $c [1] - $d;
$a = 1 - - 1;
$a = (1) - 1;
$a = new Foo() - 1;
$a = [1] - [2];
$a = f() - 1;
$a = 'x' - 1;
$a = "x" - 1;
$a = FOO - 1;
$a = __LINE__ - 1;
$a = $b ? - 1 : + 2;
function foo($a, ...   $b) { return (--   $a) * ($b   ++);}
function & bar(& $x, &   ...$y) { $z = &   $x; $q = & $x; }
$f = function & () use (& $a) {};
$f = fn & ($x) => $x;
foo(...   $args);
foo(  ... );
$a = $b & $c;
$a = $b & $c & Foo::A & $d;
$x = Foo::BAR & $x;
$x = FOO   &   $y;
function g(A&B $x, A & B ...$y): X & Y {}
$a   // c
    ++;
$a /* c */ ++;
$a
    ++;
! /* c */ $a;
!
$a;
-- // c
$a;
--	$a;
$a = ! $b ? - $c : ~ $d;
$a = &  $b;
foreach ($x as & $v) {}
foreach ($x as $k => & $v) {}
list(& $a) = $b;
[&   $a] = $b;
$s = "a - $b ++ {$c}";
for ($i = 0; $i < 3; $i ++) {}
$a = $b -- - $c;
$a = $b ++ + $c;
$a = - - $b;
$a = -- $b;
echo -1, - 1, - $a;
return -   1;
