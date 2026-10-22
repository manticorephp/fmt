<?php
namespace App;

use Foo\Bar;
use Foo\Baz as Alias;
use Other\Thing;

#[Bar]
class A {}
#[Bar, Thing]
class B {}
#[Alias(1), \Foo\Bar, Keep]
class C {}
#[Bar(x: 1)] #[Keep]
class D {}
#[
    Bar,
    Keep,
]
class E {}
#[Foo\Bar]
class F {}
#[\Foo\Bar]
#[Thing]
class G {
    #[Bar] public $a;
    #[Bar, Alias] public function m(#[Bar] $x) {}
}
#[Bar,]
class H {}
#[Keep,]
class I {}
$f = #[Bar] fn() => 1;
