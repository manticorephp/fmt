<?php

namespace Foo;

$a = 'Exception';
$b = "\\Exception";
$c = '\Exception';
$d = 'exception';
$e = 'Countable';
$f = 'Stringable';
$g = 'ArrayAccess' . 'x';
$h = 'DateTimeImmutable';
$i = 'Foo\Bar';
$j = 'Traversable' ;
$k = "Iterator\n";
$l = 'PhpCsFixer\Tokenizer\Tokens';
$m = "\\PhpCsFixer\\Tokenizer\\Tokens";
$n = ['ArrayObject', 'SplStack', 'x'];
$o = b'Exception';
$p = '\\\\Exception';
$q = '\\Exception\\';
$r = 'UnitEnum';
$s = 'BackedEnum';
$t = 'Fiber';
$u = "Exception $x";
$v = 'Symfony\Component\Finder\Finder';
$w = 'WeakMap';
$x = new \ArrayObject('ArrayObject');
#[Attr('Exception')]
function f($a = 'ArrayIterator') {}
