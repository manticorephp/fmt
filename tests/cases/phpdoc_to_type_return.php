<?php

namespace Foo\Bar;

use Traversable;

/**
 * @return string
 */
function a()
{
}

/**
 * @return int|null
 */
function b()
{
}

/**
 * @return Foo|Bar
 */
function c()
{
}

/** @return void */
function d()
{
}

/** @return static */
function e()
{
}

/** @return self */
function f()
{
}

/** @return mixed */
function g()
{
}

/** @return null */
function h()
{
}

/** @return \Foo\Baz */
function i(): ?Baz
{
}

/** @return array|Traversable */
function j() {}

/** @return iterable|array */
function k() {}

/**
 * @return int
 * @return string
 */
function l() {}

/** @return resource */
function m() {}

/** @return never */
function n() {}

/** @return null|void */
function o() {}

/** @return int|void */
function p() {}

/** @return string[] */
function q() {}

class A
{
    /** @return int */
    public function __construct() {}

    /** @return int */
    public function __destruct() {}

    /** @return int */
    public function __clone() {}

    /** @return int */
    public function r() {}

    /** @return int */
    abstract public function s();

    /** @return static */
    public static function t() {}

    /** @return $this */
    public function u() {}

    /** @return bool */
    public function v()
    {
        return fn () => 1;
    }

    /** @return string|int|null */
    public function w() {}

    /** @return \int */
    public function x() {}

    /** @return Foo\int */
    public function y() {}

    /** @return float */
    final protected function z(): string
    {
    }
}

$a = /** @return int */ function () {
};

$c = /** @return int */ fn () => 1;

$d = /** @return int */ fn&($x) => $x;

/** @return iterable */
function gen() { yield 1; }

/**
 * @phpstan-type Alias string
 * @return Alias
 */
function typed() {}

/** @return Foo|Bar|null */
function nullable()
// comment
{
}

/** @return int */
function &byRef()
{
}

/** @return true */
function tr() {}

/** @return false */
function fa() {}

/** @return list<int> */
function li() {}

/** @return Foo&Bar */
function inter() {}
