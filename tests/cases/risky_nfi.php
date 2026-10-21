<?php

namespace Foo;

use function strlen;
use function \count;

$a = strlen($b) + \strlen($b) + count($b) + \count($c);
$a = foo($b) + \foo($b) + Foo::count($b) + $x->count($b) + new count($b);
$a = \array_map('strtolower', $b) + array_map('strtolower', $b) + str_repeat('x', 3);
$a = sizeof($b) + is_null($b) + \is_null($b) + IS_NULL($b);
$a = intval(...);
function strlen($a) {}
$a = strlen($b);
#[Attr(count(1))]
class A {}
namespace Bar;
$a = strlen($b) + \foo($c);
namespace Baz {
    $a = count($b) + \strpos($c);
}
namespace {
    $a = count($b) + \strpos($c) + \count($d);
}
