<?php
class A
{
    const A = 1, B = 2;
    public const C = [1, 2], D = array(3, 4);
    private static $a = [1, 2], $b, $c = 3;
    public int $d, $e;
    protected ?string $f = null,
        $g = "x";
    var $h, /* c */ $i;
    public $j = 1,$k;
    final public const int E = 1, F = 2;

    public readonly int $l, $m;
    public function f($a, $b) { $x = 1; }
    #[Foo] public $n;
    public static $o = PHP_EOL, $p = self::A;
}
