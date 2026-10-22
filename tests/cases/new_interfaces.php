<?php
class A implements C, B, A {}
class B implements \Zed, \Foo\Bar, Baz {}
class C extends P implements Z,Y , X {}
class D implements B,
    A,
    C
{
}
interface E extends C, B, A {}
interface F extends B {}
class G implements A {}
class H implements aa, Ab, AB, ab {}
class I implements Foo\Bar, Foo\Bar\Baz, Foo, \Foo\Qux {}
$x = new class implements C, A {};
$y = new class extends Z implements B, A {};
abstract class J implements /* c */ B, /* d */ A {}
class K implements
    // first
    Z,
    // second
    A
{}
enum L: string implements B, A { case X = 'x'; }
class M implements Longer\Name\Here, Short, Mid\Name {}
class N extends B implements \Stringable, \Countable, \ArrayAccess, \IteratorAggregate {}
