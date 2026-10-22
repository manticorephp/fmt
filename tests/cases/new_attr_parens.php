<?php
#[Foo]
class A {}
#[Foo()]
class B {}
#[Foo(), Bar, Baz(1), Qux()]
class C {}
#[Foo(
)]
function f() {}
#[Foo( )]
function g() {}
#[Foo, Bar]
#[Baz()]
class D {
    #[Attr()] public int $p;
    #[Attr(a: 1)] public function m(#[SensitiveParameter()] $x, #[X] $y) {}
    #[Attr(new Foo())] const C = 1;
}
$f = #[Foo()] fn() => 1;
$g = #[Foo] function () {};
$h = new #[Foo()] class {};
#[Foo\Bar()]
enum E { #[Foo()] case A; }
#[\Foo\Bar, \Foo()]
interface I {}
#[Foo /* c */ ( /* d */ )]
class Z {}
#[Foo(
    1,
    2,
)]
class Y {}
#[Foo(Bar::class), Baz()]
class W {}
