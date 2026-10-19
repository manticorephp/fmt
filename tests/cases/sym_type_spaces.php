<?php
function a(int$a, ?string$b, array|null$c, \Foo\Bar$d, int&...$e) {}
function b(int  $a, ?string
    $b, A&B$c, (A&B)|null$d = null) {}
function c(int /* c */ $a, int/* c */$b) {}
$f = function (int$a, $b) use ($c): int {};
$g = fn(int$a, ?array$b = null) => $a;
$h = static fn (int$a) => 1;
class A {
    public int$a;
    protected ?string  $b = null, $c;
    private static array$d = [];
    public readonly int|string$e;
    var $f;
    public int
        $g;
    public static ?self$h;
    final public const int X = 1;
    public const string/* c */Y = 'a';
    const Z = 1;
    public function m(int$a, string$b): void {}
    public function __construct(public int$p, protected readonly ?string$q = null, private array|bool$r = []) {}
    abstract public function n(callable$cb, iterable$it);
    public function o(self$a, parent$c) {}
}
interface I { public function p(int$a); const int J = 1; }
trait T { public int$a; function q(int$x) {} }
enum E: string { const int A = 1; public function r(int$a) {} }
$o = new class { public int$a; function s(int$b) {} };
function &ref(array&$a, int$b) {}
function v(int...$a) {}
function w(#[Attr] int$a, #[Attr]int$b) {}
