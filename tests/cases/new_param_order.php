<?php
/**
 * @param int $b
 * @param string $a
 * @param $c
 */
function f($a, $b, $c) {}

/**
 * @param int $x
 * @param int $y
 */
function g($a, $b) {}

/**
 * Description.
 *
 * @param string $b the b
 *                  continued
 * @param int $a
 * @return void
 * @param array $c
 */
function h($a, $b, $c) {}

/**
 * @param array{
 *     a: int,
 *     b: string
 * } $second
 * @param int $first
 */
function i($first, $second) {}

/**
 * @param int ...$rest
 * @param string $a
 */
function j($a, ...$rest) {}

/**
 * @param int &$ref
 * @param string $a
 */
function k($a, &$ref) {}

/**
 * @param string $b
 * @throws Exception
 * @param string $a
 */
fn_after($x);

/** @param int $b @param int $a */
function l($a, $b) {}

class A
{
    /**
     * @param int $b
     * @param int $a
     */
    public function m($a, $b) {}

    /**
     * @param int $z
     * @param int $y
     */
    private $prop;

    /**
     * @param callable(int $b, string $a): void $cb
     * @param int $a
     */
    public function n($a, $cb) {}
}

/**
 * @param int $b
 * @param int $a
 */
$g = fn($a, $b) => 1;

/**
 * @param int $b
 * @param int $a
 */
