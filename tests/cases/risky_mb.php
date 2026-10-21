<?php

namespace Foo;

use function strlen;
use function \strtolower as lower;
use function Other\strpos;

$a = strlen($a) + strlen($a, 1) + \strlen($a) + STRLEN($a) + strpos($a, $b) + strpos($a) + substr($a, 1, 2) + substr($a, 1, 2, 3);
$a = str_split($a) + stripos($a, $b, 1) + strtolower($a) + strtoupper($a) + substr_count($a, $b, 1, 2) + str_pad($a, 5) + trim($a, " ") + ltrim($a) + rtrim($a, 'x', 3);
$a = $x->strlen($a) + Foo::strlen($a) + new strlen($a) + strlen(...) + strlen(foo(1, 2));
function strtolower($x) {}
$a = strtolower($a);
