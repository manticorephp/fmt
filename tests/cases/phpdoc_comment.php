<?php
/** header */
declare(strict_types=1);
/** after declare */
namespace A;

/** @var int $x */
$x = 1;
/** @var int $y */
$y .= 2;
/** before echo $x */
echo $x;
/** list $a */
list($a, $b) = f();
/** @var array $c */
[$c, $d] = g();
/** for $i */
for ($i = 0; $i < 3; $i++) {}
/** foreach $x */
foreach ($x as $v) {}
/** nope */
foreach ($x as $v) {}
/** @var Foo $o */
(function () {})();
/** a call */
foo();
/** @return int */
return 1;
enum E
{
    /** a case */
    case A;
}
switch ($x) {
    /** a switch case */
    case 1:
        break;
}
class C
{
    use T;
    /** @var int */
    #[Attr]
    public $p;
    /** static call */
    public static function f()
    {
        /** static:: */
        static::g();
        /** static var */
        static $s = 1;
        /** @var int */
    }
    public int $h {
        /** hook */
        get => 1;
    }
}
/** before close tag */ ?>
<?php /** ignored? */ foo();
