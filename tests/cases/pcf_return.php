<?php
function a() {
    $a = 1;
    return $a;
}
function b() {
    $a = foo();
    return $a;
}
function c() {
    $a = 1;
    $b = 2;
    return $b;
}
function d($x) {
    if ($x) {
        $r = 1;
        return $r;
    }
    $r = 2;
    return $r;
}
function e() {
    $a = 1; // comment
    return $a;
}
function f() {
    $a = 1;
    /* c */
    return $a;
}
function g() {
    $a = 1;

    return $a;
}
function h() {
    $a = [
        1,
        2,
    ];

    return $a;
}
function i(&$ref) {
    $a = 1;
    return $a;
}
function &j() {
    $a = 1;
    return $a;
}
function k() {
    global $x;
    $a = 1;
    return $a;
}
function l() {
    static $x;
    $a = 1;
    return $a;
}
function m() {
    $a = $GLOBALS['x'];
    return $a;
}
function n() {
    $a = function () { $b = 1; return $b; };
    return $a;
}
function o() {
    $a = new class { function f() { $z = 1; return $z; } };
    return $a;
}
function p() {
    $a = match ($x) { 1 => 2, default => 3 };
    return $a;
}
function q() {
    $a = static function () { return 1; };
    return $a;
}
function r() {
    $a = function () use ($b) { return 1; };
    return $a;
}
function s() {
    try {
        $a = foo();
        return $a;
    } catch (Exception $e) {
        echo $a;
    }
}
function t() {
    try {
        $a = foo();
        return $a;
    } finally {
        echo $a;
    }
}
function u() {
    try {
        $a = foo();
        return $a;
    } catch (Exception $e) {
        echo $b;
    } finally {
        echo $c;
    }
}
function v() {
    /** @var Foo $a */
    $a = foo();
    return $a;
}
function w() {
    /** @var Foo */
    $a = foo();
    return $a;
}
function x() {
    $A = 1;
    return $a;
}
function y() {
    $a = 1; return $a;
}
function z() {
    $a = 1;
    return $a
    ?>
    <?php
}
function aa() {
    $this->x = 1;
    return $this->x;
}
function bb() {
    $a = $b = 1;
    return $b;
}
function cc() {
    foo(); $a = 1;
    return $a;
}
function dd() {
    $a = ($b + 1) * 2;
    return $a;
}
function ee() {
    $a = foo($x, function ($y) { $q = 1; return $q; });
    return $a;
}
function ff() {
    $$a = 1;
    $b = 2;
    return $b;
}
function gg() {
    $b = eval('1');
    return $b;
}
function hh() {
    $b = include 'x';
    return $b;
}
abstract class A {
    public function m() {
        $a = $this->x;
        return $a;
    }
    abstract function n();
    public function o() {
        if ($x) {
            $a = 1;
        }
        return $a;
    }
    function p() {
        $a = 1;
        return $a;
        $b = 2;
        return $b;
    }
    function q() {
        {
            $a = 1;
        }
        return $a;
    }
}
interface I { function f(); function g(); }
$f = function () {
    $a = 1;
    return $a;
};
$f = fn () => 1;
function ii() {
    $a = 1;
        return $a;
}
function jj() {
	$a = 1;
	return $a;
}
function kk() {
    $a = 1;
    return $a ?>x<?php
}
function ll() {
    $a = <<<EOT
x
EOT;
    return $a;
}
function mm() {
    $a = $x
        ? 1
        : 2;
    return $a;
}
function nn() { $a = 1; return $a; }
function oo() {
    $a = 1;

    // comment before return
    return $a;
}
