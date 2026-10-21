<?php

namespace Foo\Bar;

abstract class Foo
{
    const BAZ = 1;
    const BAR = Foo::BAZ;
    const QUX = \Foo\Bar\Foo::BAZ;
    const NOPE = \Other\Foo::BAZ;

    public function a(Foo $x, ?Foo $y, Foo|Bar $z, \Foo\Bar\Foo $w): Foo
    {
        $a = new Foo();
        $b = new \Foo\Bar\Foo();
        if ($a instanceof Foo) {}
        $c = Foo::BAR + \Foo\Bar\Foo::BAZ + FOO::BAZ;
        $d = $this->Foo + Foo\Bar::X;
        $e = function () { return Foo::BAZ; };
        $f = fn () => Foo::BAZ;
        $g = new class { function f() { return Foo::BAZ; } };
        return Foo::BAZ;
    }
    public function b(Baz|Foo $x = null, $y = Foo::BAZ) {}
    abstract function c(Foo $x): ?Foo;
}
interface Foo2 { const A = Foo2::B; const B = 1; function f(Foo2 $x): Foo2; }
class Baz { const A = Baz::B; const B = 1; }

function f1($a = 1, $b) {}
function f2($a = 1, $b = 2, $c) {}
function f3($a = 1, ...$b) {}
function f4(int $a = null, $b) {}
function f5(?int $a = null, $b) {}
function f6(int|null $a = null, $b) {}
function f7($a = [1, 2], $b, $c = FOO_C, $d) {}
function f8($a = 1 /* c */, $b) {}
function f9($a = /* c */ 1, $b) {}
function f10($a  =  1  ,  $b) {}
$x = function ($a = 1, $b) {};
$y = fn ($a = 1, $b) => $a;
class M { public function __construct(public int $a = 1, private $b) {} }
function f11(#[Attr] $a = 1, $b) {}

function v1() { return; }
function v2() { return 1; }
function v3() { }
function v4() { yield 1; }
function v5(): int { return 1; }
function v6() { $a = function () { return 1; }; }
function v7() { $a = new class { function f() { return 1; } }; }
/** @return int */
function v8() { }
/** @return void */
function v9() { return 1; }
function __construct() {}
function __toString() {}
abstract class V { 
    /** @return void */
    abstract function a();
    /** @return int */
    abstract function b();
    abstract function c();
    #[Attr]
    /** @return void */
    abstract public function d();
    /** @return void */
    #[Attr(1)] abstract public function e();
    // @return void
    abstract function f();
    function g() { throw new \Exception(); }
    function h() { yield from foo(); }
}
interface VI { /** @return VOID */ function a(); function b(); }
$a = function () {};
$a = function () use ($x) { echo 1; };
$a = fn () => 1;
