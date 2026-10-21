<?php

abstract class A
{
    public function a() {}
    public static function b() {}
    final public function c() {}
    abstract public function d();
    public abstract function e();
    public function __construct() {}
    public function __toString() {}
    protected function f() {}
    private function g() {}
    public $x;
    public const C = 1;
    public static $y;
    public function h() { return function () { return 1; }; }
    static public function i() {}
    public function j() { new class { public function k() {} }; }
    public function __CLONE() {}
}
class B { public function a() {} }
abstract class C { abstract function a(); public function z() {} }
$x = function () {};
abstract class D { public static function s() {} public function __invoke() {} public function & r() {} }
