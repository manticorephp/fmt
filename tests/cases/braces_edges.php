<?php
$a = function () { return 1; };
$b = function () { return 1; }; $c = function () {
    return 2; };
$d = fn () => function () { $x = 1; return $x; };
$e = new class { };
$f = new class { public $x; };
$g = new class extends Foo implements Bar { };
$h = function () use ($a) { };
function single() { return 1; }
function emptyf() {}
if ($x) { foo(); }
if ($x) {} else {}
while (true) { break; }
foreach ($a as $v) { echo $v; }
try { a(); } catch (E $e) { b(); } finally { c(); }
switch ($x) { case 1: break; }
$m = match ($x) { 1 => 2, default => 3 };
class K { public function a(): ?int { return 1; } public function b(): A|B { return 1; } public function c(): (A&B)|null { return null; } }
class L
{
    public function x(
        int $a,
    ): void {
    }
    public function y(
        int $a
    ) /* c */ {
    }
    public function z() /* a */ /* b */ {
    }
    public function w() /* a */
    {
    }
    public function v() { /* only block */
    }
    public function u() { # hash comment
        return 1;
    }
}
if ($x) /* c */ {
    y();
}
if ($x) // c
{
    y();
}
if ($x) /* a */ // b
{
    y();
}
interface M { }
trait N {}
enum O: string { case A = 'a'; }
do { $i++; } while ($i < 3);
declare(ticks=1) { foo(); }
$p = new class() { public function q() { return function () { return 1; }; } };
