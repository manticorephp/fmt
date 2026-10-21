<?php
/**
 * @param int|int $a
 * @param string|null|string $b
 * @param array<int|int, string|string> $c
 * @param Foo&Foo $d
 * @param int|string|INT $e
 * @return int|int|null
 * @var int|int
 * @throws A|B|A
 * @method int|int foo(int|int $a)
 * @property string|string $p
 * @param (int|int)[] $f
 * @param callable(int|int): void $g
 * @param Foo|\Foo $h
 * @param ?int|int $i
 * @param array{a: int|int, b: string} $j
 * @param int | int $k
 */
function f() {}
/** @var string|string $x */
$x = 1;
/**
 * @param int $a
 * @var string
 */
$y = 2;
/**
 * @method static int|int foo(callable(int|int): void $a, string|string $b)
 * @method self|self bar()
 */
class A {}
