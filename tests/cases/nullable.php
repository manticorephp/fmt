<?php
function f(int|null $a, null|string $b, ?int $c, int|string|null $d, null $e, NULL|Foo $f, \Foo\Bar|null $g,array|null $h): null|int {}
function g(): static|null {}
$x = fn (null|int $a): int|null => $a;
$y = function (null | int $a) use ($b): int /* c */ | null {};
class A
{
    public null|int $a;
    public int|null $b, $c;
    public null|int $d, $e;
    private static null|array $f = null;
    public function __construct(public null|int $x, readonly int|null $y) {}
    public function m(callable|null $c): ?self {}
    abstract public function n(null|A $a);
}
interface I { public function o(null|int $a): null|int; }
function h(null|int $a, null|int $a) {}
function i((\A&\B)|null $a) {}
