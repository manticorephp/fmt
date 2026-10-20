<?php
class A
{
    const X = 1;
    const Y = 2;
    public $a;
    public $b;
    protected int $c = 1;
    /** doc */
    public $d;
    function m1() {}
    function m2() {}


    function m3() {}
    // comment
    function m4() {}

    // comment

    function m5() {}
    /** doc */
    function m6() {}
    #[Attr]
    function m7() {}
    #[Attr]
    #[Attr2]
    function m8() {}
    /** doc */
    #[Attr]
    function m9() {}
    abstract function m10();
    abstract function m11();
    public static function m12(): void { }   // trailing
    public function m13() {} // trailing

    public function m14() {} /* trailing */ public $z;
    public $z1; public $z2;
    use T;
    use U;
    use V { a as b; }
    use W;
}
class B {
    public $a; }
class C { function f() {} function g() {} }
class D {
    function f() {}

}
class E {

    function f() {}
}
class F {
    // only comment first
    function f() {}
}
class G {

    /** doc */
    function f() {}
    function g() {}
    // c1
    // c2
    function h() {}
}
interface I {
    const A = 1;
    function a();
    function b();
}
trait T {
    use U;
    public $a;
    function a() {}
}
enum E1: string {
    case A = 'a';
    case B = 'b';
    const C = 1;
    function f() {}
    case D = 'd';
}
$o = new class {
    public $a;
    function f() {}
    function g() { return new class { public $x; function h() {} function i() {} }; }
    public $b;
};
class H {
    public function __construct(public int $a, public int $b) {}
    public int $p { get => 1; }
    public int $q { get => 1; }
}
class J { public $a;
    public $b;
    /**
     * multi
     */
    public function f() {} public function g() {}


    // tail comment
}
