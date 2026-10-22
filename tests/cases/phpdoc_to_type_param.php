<?php

namespace Foo\Bar;

use Traversable;
use Other\Thing as Alias;

/**
 * @param string $a
 * @param int|null $b
 * @param Foo|Bar $c
 * @param \Foo\Baz $d
 * @param array<string> $e
 * @param string[] $f
 * @param mixed $g
 * @param null|string $h
 * @param int|string $i
 * @param array|Traversable $j
 * @param resource $k
 * @param static $l
 * @param void $m
 * @param \int $n
 * @param Foo\int $o
 * @param callable $p
 * @param self $q
 * @param iterable $r
 * @param object $s
 * @param float|int|null $t
 * @param Alias $u
 * @param double $v
 * @param boolean $w
 */
function f($a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k, $l, $m, $n, $o, $p, $q, $r, $s, $t, $u, $v, $w)
{
}

/**
 * @param string $a
 * @param int $b
 */
function g(string $a, $b, &$c, ...$d, $e = null)
{
}

/**
 * @param string $a
 * @param int $b
 * @param Foo $c
 * @param Foo $d
 */
function h(&$a, & $b, $c = null, $d = [])
{
}

class A
{
    /**
     * @param int $x
     */
    public function __clone($x)
    {
    }

    /**
     * @param int $x
     */
    public function __destruct($x)
    {
    }

    /**
     * @param int $x
     * @param string $y
     */
    final public static function m($x, $y)
    {
    }

    /**
     * @param int $x
     */
    // comment
    abstract protected function n($x);

    /** @param bool $x */
    public function __construct(public $x, private $y)
    {
    }

    /** @param Foo|Bar|null $x */
    public function o($x)
    {
    }

    /**
     * @param array|\Traversable $x
     * @param iterable|array $y
     * @param \Traversable|iterable $z
     * @param self|static $w
     */
    public function p($x, $y, $z, $w)
    {
    }
}

$f = /** @param int $x */ function ($x) {
};

$g = /** @param int $x */ fn ($x) => $x;

/**
 * @phpstan-type Alias string
 * @param Alias $x
 * @param int $y
 */
function t($x, $y)
{
}

/** @param int $a */
function u($a, $b)
{
}

/** @param int $b */
function v($a,
    $b)
{
}

/**
 * @param int $a
 * @param int $a
 */
function w($a)
{
}

/**
 * @param Foo $a
 * @param Bar $b
 */
function x(
    $a,
    $b,
) {
}

/**
 * @param TypeA|TypeB $a
 * @param namespace\TypeC $b
 * @param \Foo\Bar|\Foo\Baz $c
 * @param true $d
 * @param false $e
 * @param Foo\Bar[] $f
 * @param list<int> $g
 * @param int<0, 5> $h
 * @param ?int $i
 * @param (int|string) $j
 * @param int&string $k
 * @param Foo&Bar $l
 * @param class-string $m
 */
function y($a, $b, $c, $d, $e, $f, $g, $h, $i, $j, $k, $l, $m)
{
}
