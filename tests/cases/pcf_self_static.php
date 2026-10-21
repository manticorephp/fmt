<?php
final class A
{
    private static $a = 1;
    public function f()
    {
        $x = static::class . static::g() . static::$a . new static() . ($y instanceof static);
        $f = function () { return static::g() . new static; };
        $g = function () {
            return new class () { function h() { return static::z(); } } . static::q();
        };
        $h = fn() => static::g();
        return static function () { return static::$a; };
    }
    public static function g() { return new static(); }
    public static function k(): static { return static::g(); }
}
class B
{
    public function f() { return static::g(); }
}
abstract class C { function f() { return static::g(); } }
final readonly class D { function f() { return static::g(); } }
$a = new class() {
    public function getBar()
    {
        return static::class;
    }
};
class E { function f() { return new class { function z() { return static::c(); } }; } }
enum F
{
    public const A = 123;
    public static function bar(): void
    {
        echo static::A;
        $x = new static();
        $y = $z instanceof static;
        $q = function () { return static::A; };
        $w = static fn() => static::A;
    }
}
final class G {
    function a() { static $x = 1; return static::b() . static::$y . A::class; }
    function b() { return static::foo; }
}
class H { function x() { return Foo::class; } }
final class I { function x() { return Foo::class . static::X; } }
