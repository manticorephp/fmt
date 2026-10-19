<?php
$a = array(1, 2);
$b = array (1, array(2, 3));
$c = ARRAY(
    1,
    2
);
$d = array /* c */ ( 1 );
$e = (array) $x;
function f(array $a = array()) {}
$f = [
    1,
    [2, 3]
];
$g = foo(
    1,
    2
);
$h = match ($x) {
    1 => 2,
    default => 3
};
[$i, $j] = [
    $k,
    $l
];
list(
    $m,
    $n
) = $o;
function g(
    $a,
    $b
) {}
$p = fn (
    $a
) => 1;
$q = new Foo(
    1
);
$r = $obj->method(
    1 /* c */
);
$s = [
    <<<EOT
    x
    EOT
];
$t = foo(1,
    2);
$u = [1, 2,
];
$v = isset(
    $a,
    $b
);
$w = foo(...);
$x = [
    "a
b"];
$y = $z(
    1
);
$aa = new class(
    1
) {};
