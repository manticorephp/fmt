<?php
function a(int $a = null, ?int $b = null, string $c = 'x', $d = null, mixed $e = null, null $f = null, int|string $g = null, int|null $h = null, A&B $i = null, \Foo\Bar $j = NULL) {}
function b(int $a = null, string ...$rest) {}
function c(int &$a = null, array $b = [], callable $c = null, iterable $d = null, self $e = null) {}
$f = function (int $a = null) {};
$g = fn(int $a = null) => $a;
class A { public function __construct(public int $a = null, protected readonly string $b = null, private ?array $c = null) {} function m(Foo $x = null, ?Bar $y = null, Baz|Qux $z = null) {} }
function d(int
    $a = null, /* c */ string $b = null) {}
function e(#[Attr] int $a = null) {}
function f(int $a = null, string $b = null, array $c = null) {}
function g(A|B|C $x = null, (A&B)|C $y = null, A&B $z = null, ?A $w = null) {}
function h(int $a = Null, int $b = nuLL) {}
function i(int $a = null_const, int $b = [null]) {}
