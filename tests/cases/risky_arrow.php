<?php

$a = foo(function ($a) use ($b) { return $a + $b; });
$a = foo(function ($a) { return $a; });
$a = function () { return; };
$a = function &() { return 1; };
$a = function ($x) use (&$y) { return $x; };
$a = function ($x) use ($y): int { return $x + $y; };
$a = function ($x) use ($y, $z,) { return require $y; };
$a = function () { return include 'x.php'; };
$a = function () { $x = 1; return $x; };
$a = function () { return 1; return 2; };
$a = function () { return function () { return 1; }; };
$a = static function (int $a): ?string { return $a ? 'x' : null; };
$a = function () {
    return [1, 2,
        3];
};
#[Attr] function () { return 1; };
$a = function ($x) /* c */ use /* d */ ($y) /* e */ { /* f */ return /* g */ $x; /* h */ };
function named() { return 1; }
$a = function () { return $this->a; };
$a = function ($x) use ($y) { return isset($y); };
$a = rand() + rand(1, 2) + srand() + srand(5) + getrandmax() + mt_rand(1, 2) + mt_srand() + mt_getrandmax() + \rand() + random_int(1, 2) + Rand(3);
$a = $x->rand() + rand(1, 2, 3) + rand(1, 2);
