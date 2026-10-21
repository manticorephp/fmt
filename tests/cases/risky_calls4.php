<?php

namespace Foo;

$a = chop($b);
close($b);
$a = doubleval($b);
fputs($b, $c);
$a = \is_double($b);
$a = JOIN($glue, $pieces);
$a = key_exists($key, $array) + sizeof($b);
$a = $x->pos($array) + Foo::sizeof($b) + \sizeof($b);
$a = imap_header($i, 1) . pg_exec($c, $d) . mbereg_search_getregs() . mktime() . mktime(1) . gmmktime();
$a = mktime(foo(1, 2), 3);
$a = mktime(...);
$a = new sizeof();
function sizeof($a) {}
$a = sizeof($b);
array_push($a, $b);
array_push($a->b['c'], $b, );
array_push($a, $b, $c);
array_push($a, ...$b);
\array_push(self::$a[1]->c, foo($b, $c) + 1);
$x = array_push($a, 1);
if ($a) array_push($a, $b);
function g() { array_push($a, yield $b); }
array_push($a, $b or $c);
array_push($a, [1, 2]);
array_push(Foo::$bar, $x) ?>
<?php
array_push($a /* c */ , /* d */ $b /* e */ );
array_push(foo(), 1);
array_push($$a, 1);
array_push(static::$a, 1);
array_push($a::$b, fn() => 1);
