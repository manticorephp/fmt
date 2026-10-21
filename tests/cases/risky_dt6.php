<?php

/**
 * @param int[] $a
 * @param (int|string)[] $b
 * @param ?string[][] $c
 * @param array<int> $d
 * @param array<string, int> $e
 * @param array{a: int}[] $f
 * @return array<array<int>>|int[]
 * @var list<array<int, string>> $g
 * @var "x[]" $h
 * @param array<int|string> $i
 * @param array<int, array<string>> $j
 * @param array $k
 * @param Foo[] | Bar[] $l
 */
function f() {}

/** @return \Foo\Bar[] */
function g() {}
