<?php
namespace B;

use Stringable;
use Foo\Bar as Baz;
use Stringable as Str;

class A implements Stringable { public function __toString() { return ''; } }
class B implements Str { public function __toString() { return ''; } }
class C implements Baz { public function __toString() { return ''; } }
class D { public function __toString() { return ''; } }

namespace C;

use Foo\{Bar, Stringable as S2};
use function Foo\bar;

class A implements Stringable { public function __toString() { return ''; } }
class B implements S2 { public function __toString() { return ''; } }
class C implements Bar { public function __toString() { return ''; } }
