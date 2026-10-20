<?php
function a($x) {
    $a = 1;
    return $a;
}
function b($x) {
    foo();

    return 1;
}
function c($x) {
    if ($x) {
        return 1;
    }
    return 2;
}
function d($x) {
    foo();
    // comment
    return 1;
}
function e($x) {
    foo();
    // comment one
    // comment two
    return 1;
}
function f($x) {
    foo();

    // comment
    return 1;
}
function g($x) {
    foo(); return 1;
}
function h($x) {
    foo();	return 1;
}
function i($x) {
    foo();
    /* c */ return 1;
}
function j($x) {
    foo();
    /** doc */
    return 1;
}
function k($x) {
    try {
        foo();
    } catch (E $e) {
        throw $e;
    }
    throw new E();
}
function l($x) {
    foo();
    throw new E();
    foo();
    break;
    continue;
}
declare(strict_types=1);
foo();
declare(ticks=1);
function m($x) {
    do {
        foo();
    } while ($x);
    while ($x) { foo(); }
    foreach ($x as $y) { foo(); }
    for (;;) { break; }
    if ($x) { foo(); }
    switch ($x) { case 1: foo(); break; default: foo(); }
    yield 1;
    yield from foo();
    exit(1);
    goto a;
    include 'a';
    require_once 'b';
    $z = 1;
    /** @var int $z */
    $y = 2;
    return $z;
}
enum E { case A; case B; }
switch ($a) {
    case 1:
        foo();
        break;
    case 2:
        foo();
    case 3:
}
$x = function () {
    foo();
    return 1;
};
class K {
    public function m() {
        foo();
        return 1;
    }
    /** doc */
    public function n() {}
    public $p;
    /** doc2 */
    public $q;
}
