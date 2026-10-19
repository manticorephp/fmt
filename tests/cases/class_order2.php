<?php
class B { use T1; public $x; }
class C { public $x; /* c */ use T1; /* d */ }
class D
{
    public $x;

    /** doc */
    use T1;
}
enum E { case A; use T; const B = 1; }
trait F { public function f() { return new class { public $a; use T; }; } use T; }
interface I { const A = 1; }
class G {}
abstract class H { abstract protected function a(); public static $s; private readonly int $r; use T; }
