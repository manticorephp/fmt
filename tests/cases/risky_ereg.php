<?php

$a = ereg('abc', $s) + eregi("x/y", $s) + ereg_replace('a#b/c', 'z', $s) + eregi_replace('^a', 'b', $s) + split(',', $s) + spliti('a|b', $s);
$a = ereg('a/b#c!d', $s) + ereg('[', $s) + ereg($p, $s) + ereg('a' . 'b', $s) + \ereg('x', $s, $m);
$a = ereg(b'abc', $s) + ereg("a\"b", $s) + ereg('a\'b', $s) + EREG('abc') + ereg('abc'  ,  $s);
$a = $x->ereg('abc', $s) + Foo::split('a', $s) + ereg('abc');
function split() {}
$a = split(',', $s);
$a = ereg('(?<n>a)(?<n>b)', $s) + ereg('\\d', $s) + ereg('a\\\\b', $s);
