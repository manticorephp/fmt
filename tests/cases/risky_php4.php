<?php

class A { public function A($x) { $this->x = $x; } }
class B { function B() { $this->B(); } function __construct() {} }
class C { function C($a, $b) { $this->__construct($a, $b); } function __construct($a, $b) {} }
class D { function __construct($a) { $this->D($a); } function D($a) {} }
class E extends P { function E() { parent::E(); $this->E(); P::E(); } }
class F extends P { function f() { parent::F(); $this->F(); } }
class G { static function G() {} }
abstract class H { abstract function H(); }
class I { function I() { $this->__construct(); } }
class J extends \P\Q { function J($a) { \P\Q::J($a); parent::Q($a); } }
class K { /* c */ public final function K() {} }
$x = new class { function F() {} };
class L { function l() {} function __construct() { $this->l(); } }
class M { function M() { return $this?->__construct(); } }
class N extends O { function N() { $this?->O(); } }
