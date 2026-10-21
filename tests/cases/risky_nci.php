<?php

namespace Foo;

use const PHP_VERSION;
use const A\B, C\D as E;
use const X\{Y, Z};

echo PHP_VERSION, M_PI, \M_PI, E_ALL, \FOO, FOO, PHP_INT_MAX, true, TRUE, \true, null, \NULL, false;
echo Foo::PHP_EOL, $x->PHP_EOL, PHP_EOL::class, new PHP_EOL, namespace\PHP_EOL, Bar\PHP_EOL, \Bar\PHP_EOL;
function f(int $a = PHP_INT_MAX, ?X $b = null): PHP_EOL|null {}
const MY = PHP_EOL;
class K { const A = PHP_EOL; public int $x = PHP_INT_SIZE; function g() { return E_ERROR; } }
$a = E_ALL | E_STRICT;
$a = $b[E_ALL] + "x" . M_E;
goto M_PI;
M_PI: echo 1;
try {} catch (E_ALL $e) {}
#[Attr(PHP_EOL)]
class L {}
$x = [PHP_EOL => PHP_EOL];
switch ($a) { case PHP_EOL: break; }
echo \DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
namespace Bar;
echo PHP_VERSION, \PHP_VERSION_ID, \foo;
namespace Baz {
    echo PHP_VERSION, \UNKNOWN_C, \Foo\PHP_EOL;
}
namespace {
    echo PHP_VERSION, \UNKNOWN_C;
}
