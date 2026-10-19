<?php
namespace Foo\Bar;

use Baz\Exception;
use function Baz\strlen;
use function strtoupper;
use function Other\{ARRAY_MAP, sort};
use function Baz\f as COUNT;

STRLEN($a);
\STRLEN($a);
Strtoupper($a);
$x->STRLEN();
X::STRLEN();
new STRLEN();
function STRLEN() {}
function foo() { return STRLEN($a) + COUNT($b) + Sort($c) + ARRAY_MAP($d); }
class A { public function STRTOLOWER() {} function g() { return STRTOLOWER($a) . $this->STRTOLOWER(); } }
$f = STRLEN(...);
#[STRLEN(1)]
class B {}
#[Attr(STRLEN(1))]
class C {}
\Foo\Bar\STRLEN($a);
namespace\STRLEN($a);
\exception::class;
new \EXCEPTION();
new Exception();
new \exception;
try {} catch (\exception $e) {}
function h(\ArrayObject $a, \arrayobject $b, \Foo\ArrayObject $c): \exception {}
\STDCLASS::class;
$d instanceof \stdclass;
$e = \stdclass::FOO;
$f = \stdclass;
