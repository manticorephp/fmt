<?php

namespace Foo;

$a = function () { echo $b; };
$a = function ($x) use ($y): int { return $x + $y; };
$a = fn ($x) => $x * 2;
$a = fn ($x) => fn ($y) => $x + $y;
$a = static function () {};
$a = static fn () => 1;
$a = function () { return $this->a; };
$a = fn () => $this->a;
$a = function () { return function () { return $this; }; };
$a = function () { return new class { function f() { return $this; } }; };
$a = function () { return new class extends Foo { function f() { return 1; } }; };
$a = function () { return parent::foo(); };
$a = function () { include 'x.php'; };
$a = function () { eval('return 1;'); };
$a = function () { $h = ${$g}; };
$a = function () { $$a = 1; };
$a = function () { return $THIS; };
$a = (function () { return 1; })->bindTo($o);
$a = (function () { return 1; })->call($o);
$a = ( /* c */ function () { return 1; } )->BINDTO($o);
$a = foo(function () { return 1; })->bindTo($o);
$a = (function () { return 1; })();
$a = array_map(function ($x) { return $x; }, $b);
$a = array_map(fn ($x) => [$x, $b[0]], $b) + [fn () => 1];
$a = function &() { return $x; };
$a = (fn () => 1)->bindTo($o);
class A
{
    public function f()
    {
        $a = function () { return 1; };
        return fn () => static::class;
    }
}
function g() { return function () { return 1; }; }
$a = fn () => function () { return $this; };
$a = fn (int $x = 1): int => $x ?: ($y ? 1 : 2);
