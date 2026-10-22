<?php
use Stringable;

class A { public function __toString() { return ''; } }
class B implements Stringable { public function __toString() { return ''; } }
class C extends A { public function __toString() { return ''; } }
class D implements Foo, Stringable { public function __toString() { return ''; } }
class E implements Foo, \STRINGABLE { public function __toString() { return ''; } }
$x = new class { public function __toString() { return ''; } };
$y = new class implements Foo { public function __toString() { return ''; } };
$z = new class(1) extends A { public function __toString() { return ''; } };
