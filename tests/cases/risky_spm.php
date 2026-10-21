<?php

class A
{
    public function a() { return $this->b() + $this->c() + $this->d(); }
    private function b() { return 1; }
    private function c() { return $this->b() + 2; }
    private function d() { return $this->x; }
    private function e() { return function () { return 1; }; }
    private function f() { return $this->f(); }
    private function g() { return debug_backtrace(); }
    private static function h() {}
    static private function i() {}
    final private function j() {}
    private function __construct() {}
    private function k() { $o = new class { function m() { return $this->z; } }; return 1; }
    private function l() { return $this->L(); }
    private function L() { return 1; }
    private function m() { return $this->n(); }
    private function n() { return $this->m(); }
    abstract private function o();
    private function p() { return $this->q(...); }
    private function q() { return 1; }
}
class B { private function z() { return $this->y(); } private function y() {} }
