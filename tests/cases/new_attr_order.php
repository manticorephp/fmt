<?php
namespace App;

use Zed\Z;
use Alpha\A as Al;
use Mid\M;

#[Z, Al, M]
class A {}
#[Z]
#[Al]
#[M]
class B {}
#[Z, Al]
#[M, Al]
class C {}
#[\Zed\Z, A, \B]
class D {}
#[
    Z(1),
    Al(2),
    M,
]
class E {}
#[Z(1, 2), A] #[B] #[A(3)]
class F {
    #[b, a] public $x;
    #[c] #[b(1)] #[a]
    public function m(#[z, a] $p, #[Z] #[A] $q) {}
}
#[Foo\Bar, Foo\Baz, Foo]
class G {}
#[Z /* c */, A]
class H {}
#[Z,
  A,
  M]
class I {}
$f = #[b, a] fn() => 1;
#[Bb, AA, aa, Aa]
class J {}
