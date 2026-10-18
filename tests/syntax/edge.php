<?php
class Outer
{
    use T1, T2 { T1::a as b; }
    public function m()
    {
        $f = function () use ($x) { return new class { use Inner; public function g() { return function () use ($y) {}; } }; };
        $o = (new Foo)->bar;
        $p = ($a ? new B : new C)->x;
        $q = [new A, (new B)];
        return (new class { use Z; });
    }
    public function n(public_ $a) { return $this->{'x'}; }
}
$a-> {$b};
$a->
{$b};
$x = "a{" . '{' . "}";
$s = "{$a}{$b}{$c['k']}${d}";
$s = "a{ $b }";
$t = "${a['k']}";
[$a, &$b] = $arr;
[[$a, $b], [$c, $d]] = $arr;
['k' => [$a], 'j' => $b] = $arr;
foreach ($arr as ['id' => $id, 'tags' => [$t1, $t2]]) {}
foreach ($arr as $k => &$v) {}
[$a, $b] = [$b, $a] = [1, 2];
$z = [$a, $b] === [1, 2];
$z = [1, 2][0];
$z = 'abc'[0];
$z = $a[0][1]['k'];
$z = $obj::CONSTS[0];
$z = ['x'][0] ?? [];
fn() => [$a, $b] = $c;
function f(): array|(A&B) {}
function g((A&B)|C $x) {}
$f = function (): (A&B)|null {};
interface I3 { public function a(): static; public function b(): ?static; }
$x = $y instanceof static;
$x = $a ? static::f() : static::g();
const FOO = 1;
function use_() { use_test(); }
class UT { use A; function f() { use_(); } }
trait TT { use A { a as protected; } }
enum EE { use Tr; case X; }
interface II { const A = 1; }
$d = new DateTime(...);
$cb = $x?->foo(...);
$arr = [function () {}, fn() => 1, static fn(): int => 2];
echo $a?->b['c'];
print "x" . $a?->b;
label2: $a = 1;
if ($a) { goto label2; }
$m = match(true) { default => null };
$r = $a ? fn() => 1 : fn() => 2;
$v = $cond ? ['a'] : ['b'];
class Pr { public function __construct(public readonly array $a = [], protected int|string $b = 0, private ?Foo $c = null) {} public function __CONSTRUCT(private $x) {} }
function __construct(public $notPromo) {}
abstract class Q { abstract function __construct(private $y); }
