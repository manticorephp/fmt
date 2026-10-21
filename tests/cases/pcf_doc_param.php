<?php
/**
 * Doc.
 */
function a($a, int $b, $c = null, ?string $d = null, $e = [], &$f, ...$g) {}

/**
 * Doc.
 *
 * @param int $a
 *
 * @return void
 */
function b($a, $b) {}

/**
 * @param string $b
 */
function c($a, $b, $c = 'x') {}

/** One liner */
function d($a) {}

/**
 * @inheritDoc
 */
function e($a) {}

/**
 * Doc.
 */
function f($a, array $b = null, $c = NULL) {}

abstract class A
{
    /**
     * Doc.
     */
    public static function g($a, ... $rest) {}

    /**
     * Doc.
     */
    abstract protected function h(self $a, $b);

    /**
     * Doc.
     */
    final public function i(
        #[Attr] $a,
        /* c */ $b = 1, // c
        $c,
    ) {}

    /**
     * Doc.
     */
    public function __construct(private $a, public readonly array $b = [1, 2], protected int $c = 0) {}

    /**
     * Doc.
     * @param $a
     * @return int
     */
    function j($a, $b) {}

    /**
     * Doc.
     */
    function k() {}

    /**
     * Doc.
     */
    #[Attr]
    function l($a) {}

    /**
     * Doc.
     */
    function m(\Foo\Bar $a, $b = Foo::BAR, $c = [1, [2, 3]], callable $d = null) {}

    /**
     * First.
     */
    /**
     * Second.
     */
    function n($a) {}

    /**
     * Doc.
     */
    // comment
    function o($a) {}

    /**
     * Doc.
     */
    function p($a): ?int {}
}
/**
 * Doc.
 */
$f = function ($a, $b) use ($c) {};
/**
 * Doc.
 */
$f = fn($a) => 1;
/**
 * Doc.
 */
static function ($a) {};
/**
 * @param int $a Description
 *   continued
 * @param $b
 */
function q($a, $b, $c) {}
/**
 * @return int
 */
function r($a) {}
/**
 * Doc.
 *
 */
function s($a) {}
/**
 * Doc.
 */
function t($a = null, $b = Null) {}
	/**
	 * Tabbed.
	 */
	function u($a) {}
