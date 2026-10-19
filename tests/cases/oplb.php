<?php
$a = $b
    && $c
    || $d
    and $e
    or $f
    xor $g;
$a = $b &&
    $c ||
    $d and
    $e;
$x = 'a'
    . 'b'
    . 'c';
$x = 'a' .
    'b' .
    'c';
$y = $a
    + $b
    - $c
    * $d
    / $e
    % $f
    ** $g
    <=> $h
    ?? $i;
$y = $a +
    $b -
    $c;
$z = $a
    ? $b
    : $c;
$z = $a ?
    $b :
    $c;
$z = $a
    ?: $c;
$z = $a ?:
    $c;
$z = $a
    ? : $c;
$z = $a ? /* c */
    $b : $c;
$z = $a /* c */
    ? $b
    : $c;
$w = $o
    ->foo()
    ->bar()
    ?->baz();
$w = $o->
    foo()->
    bar();
$w = Foo
    ::bar();
$v = $a
    & $b
    | $c
    ^ $d
    << $e
    >> $f;
$v = &$a;
function f(&$a, array &$b, ?int &$c, A &...$d) {}
$v = $a
    &$b;
$u = $a
    == $b;
$u = $a
    != $c;
$u = $a
    === $d;
$u = $a
    !== $e;
$u = $a
    < $f;
$u = $a
    >= $i;
$u = $a <=
    $h;
$u .= $a
    . $b;
$arr = [
    'a'
        => 1,
    'b' =>
        2,
];
if ($a
    && $b) {}
if ($a &&
    $b) {}
switch ($x) {
    case 1:
        break;
    default:
        break;
}
label:
$t = $a
    // c
    && $b
    /* d */
    && $c;
$t = $a // c
    && $b;
$t = $a &&
    // c
    $b;
$t = fn($x) => $x
    + 1;
$s = $a

    && $b;
$s = $a &&

    $b;
$q = $a  
    + $b;
