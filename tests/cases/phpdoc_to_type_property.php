<?php

namespace Foo\Bar;

use Traversable;

class A
{
    /** @var int */
    private $a;

    /** @var string|null */
    public $b;

    /** @var Foo|Bar */
    protected static $c;

    /** @var int */
    public $d, $e;

    /** @var int $d */
    /** @var int $e */
    public $f, $g;

    /**
     * @var int $h
     * @var int $i
     */
    public $h, $i;

    /**
     * @var int $j
     * @var string $k
     */
    public $j, $k;

    /** @var array|Traversable */
    public $l;

    /** @var callable */
    public $m;

    /** @var resource */
    public $n;

    /** @var null */
    public $o;

    /** @var void */
    public $p;

    /** @var int */
    public int $q;

    /** @var int */
    #[Attr]
    public $r;

    /** @var mixed */
    public $s;

    /** @var static */
    public $t;

    /** @var self|null */
    public $u;

    /** @var int */
    var $v;

    /** @var int */
    public readonly $w;

    /** @var int */
    public function x()
    {
        /** @var int */
        $y = 1;
    }

    /** @var Foo[] */
    public $z = [];

    /** @var iterable */
    public $aa = null, $bb;

    /** @var int|string|null */
    public $cc;
}

trait T
{
    /** @var float */
    private $x;
}

interface I
{
    /** @var int */
    const X = 1;
}

$o = new class {
    /** @var int */
    public $a;
};

/**
 * @phpstan-type Alias int
 */
class B
{
    /** @var Alias */
    public $a;

    /** @var int */
    public $b;
}

class C
{
    /** @var int */
    public $a;
    /** @var string */
    public $b;
    /** @var \int */
    public $c;
    /** @var Foo\string */
    public $d;
    /** @var true */
    public $e;
}
