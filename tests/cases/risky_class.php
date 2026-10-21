<?php

namespace Foo;

final class A
{
    final public function a() {}
    final protected function b() {}
    final private function c() {}
    public function d() {}
    final public static function e() {}
    use T1, T2;
}
class B
{
    final public function a() {}
    final private function c() {}
    private final function d() {}
    final private function __construct() {}
    final private static function f() {}
}
enum E
{
    final public function a() {}
    case X;
}
abstract class C { final public function a() {} abstract protected function b(); }
class D { public function __sleep() {} public function __wakeup() {} }
class D2 { public function __sleep() {} public function __serialize() {} public function __wakeup() {} public function __unserialize(array $x) {} }
class D3 { public function __wakeup( ) {} function __sleep(): array {} }
class D4 { public function __WAKEUP() {} }
trait TT { function __sleep() {} }
class U1
{
    use B, A;
    use /* c */ Z\Y, X;
    use C {
        C::foo as bar;
        C::a insteadof D;
    }
    use \D, \a, \A\B;

    public $x;
    use M;
    use N, M;
}
class U2 { use Zeta; /* between */ use Alpha; // c
    use Beta; }
class U3 { use b\Foo, B\Bar, a\Zed, A\Baz; }
class U4 { use Z; use z; use A; }
class U5 { use Aaaaa, B; use Bb, A { A::x as y; } }
