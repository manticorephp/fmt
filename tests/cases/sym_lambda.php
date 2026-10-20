<?php
$a = function () use ($x) { return 1; };
$a = function () use ($x, $y) { return $x; };
$a = function () use ($x, $y) { return $y; };
$a = function () use ($x, $y, $z) { return $y; };
$a = function () use ($x, $y, $z) { return $x + $z; };
$a = function () use (&$x) { return 1; };
$a = function () use (&$x, $y) { return 1; };
$a = function () use ($_GET, $_SERVER, $GLOBALS) { return 1; };
$a = function () use ($x) { return compact('x'); };
$a = function () use ($x) { return \compact('x'); };
$a = function () use ($x) { return $compact('x'); };
$a = function () use ($x) { return $$v; };
$a = function () use ($x) { return ${'x'}; };
$a = function () use ($x) { eval('1'); };
$a = function () use ($x) { include 'a.php'; };
$a = function () use ($x) { return "$x"; };
$a = function () use ($x) { return "${x}"; };
$a = function () use ($x) { return "{$x}"; };
$a = function () use ($x) { return function () use ($x) { return $x; }; };
$a = function () use ($x) { return function () { return $x; }; };
$a = function () use ($x) { return function ($x) { return 1; }; };
$a = function () use ($x) { return function () use ($y) { return $x; }; };
$a = function () use ($x) { return new class($x) { }; };
$a = function () use ($x) { return new class { function f() { return $x; } }; };
$a = function () use ($x) { return fn() => $x; };
$a = function () use ($x): int { return 1; };
$a = function &() use ($x) { return 1; };
$a = function ($p) use ($x, $p2) { return $p; };
$a = function () use ($x,) { return 1; };
$a = function () use ($x, $y,) { return 1; };
$a = function () use ( $x , $y ) { return $y; };
$a = function () use ($x /* c */, $y) { return $y; };
$a = function () use (/* c */ $x, $y) { return $y; };
$a = function () use ($x, /* c */ $y) { return $x; };
$a = function () use ($x) /* c */ { return 1; };
$a = function ()
    use ($x)
{
    return 1;
};
$a = function () use (
    $x,
    $y
) {
    return $y;
};
$a = static function () use ($x) { return 1; };
class A { function f() { return function () use ($x) { return 1; }; } }
function g() { return function () use ($a, $b) { return $b; }; }
