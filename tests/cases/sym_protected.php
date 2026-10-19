<?php
final class A
{
    protected const X = 1;
    final protected const Y = 2;
    protected $a;
    protected int $b = 1;
    protected ?int $c = null;
    protected static $d;
    protected static ?Foo $e;
    public protected(set) int $f = 1;
    protected(set) public string $g;
    protected function m() {}
    protected static function s() {}
    final protected function f() {}
    protected \Foo\Bar $h;
    protected int|string $i;
    protected readonly int $j;
    protected $k, $l;
    public function __construct(protected int $p, protected readonly string $q, public $r, protected(set) public int $t = 3) {}
    private $z;
    abstract protected function ab();
}
final class B extends C { protected $x; protected function m() {} }
final class D { use T; protected $x; }
class E { protected $x; }
abstract class F { protected $x; }
enum G { protected const A = 1; }
$o = new class { protected $x; };
final class H { protected function m() { return new class { protected $y; }; } }
final class I { /** doc */ protected /* c */ function m() {} #[Attr] protected $z; }
