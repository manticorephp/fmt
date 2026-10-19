<?php
class A
{
    var $a;
    static public $b;
    public  static $c = 1;
    const C = 1;
    final public const D = 2;
    public final const E = 3;
    function f() {}
    static function g() {}
    abstract protected function h();
    protected abstract function i();
    public
        function j() {}
    static
    private function k() {}
    readonly public int $l;
    public static ?int $m = null, $n;
    private /* c */ static $o;
    public function &p() {}
    static public function &q() {}
    public function __construct(public readonly int $x, readonly int $y, protected private(set) string $z = "") {}
    private(set) public string $w = "";
    public static function r() { return new class { function s() {} var $t; }; }
    final static public function u() {}
    protected int|string $v;
    public ?\Foo\Bar $bb;
    static int $cc;
}
interface I { function f(); const X = 1; }
trait T { static $a; function f() {} }
enum E { const X = 1; function f() {} }
$o = new class(1) { function __construct($a) {} };
