<?php
namespace A\B;

use C\D\E as F;
use function G\h;
use const I\J;
use K\{L, M\N, function o, const P};

class X extends \Y\Z implements \W\V, U\T
{
    public function m(\A\B $a, B\C $b, namespace\D $c): \E\F|\G\H { return new namespace\Q; }
}
$a = new \Foo\Bar();
$b = \Foo\bar(1);
$c = Foo\BAR;
$d = namespace\func();
$e = \strlen('x');
$f = function_exists('x');
$g = A\B::C;
$h = [\A::class, B\C::class];
instanceof_test:
$i = $x instanceof \A\B;
if ($x instanceof namespace\Foo) {}
function list_() { list('a' => $a) = $x; }
$class = 'x';
echo $x->class, $x->function, $x->list, $x::function(), $x::list;
class K { const class_ = 1; public function function() {} public function list() {} public function print() {} public static function new() {} const FUNCTION = 2; const NEW = 3; }
K::new();
echo K::FUNCTION;
$o->print();
function &print_ref() {}
