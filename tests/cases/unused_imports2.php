<?php
namespace App;

use Foo\A, Foo\B, Foo\C;
use Foo\D,
    Foo\E,
    Foo\F;
use Foo\{G, H, I};
use Foo\{
    J,
    K,
    L,
};
use function Foo\{m, n};
use Foo\Catched;
use Foo\Inst;
use Foo\Stat;
use Foo\lower;

    use Foo\Indented; // c

try {
} catch (Catched $e) {
}
$x instanceof Inst;
Stat::f();
new LOWER();
$f = function () use ($x) { return B::class . D::X . F::Y . H::y() . K::$z . m(); };
