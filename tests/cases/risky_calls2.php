<?php

namespace Foo;

settype($a, "integer");
settype($b, 'string');
settype( $c , "null" );
settype($d, "object", );
settype($e->f, "int");
$x = settype($g, "int");
settype($h, $i);
settype($j, "unknown");
settype(/* a */ $k /* b */, /* c */ "bool" /* d */);
\settype($l, "FLOAT");
if ($z) { settype($m, "array"); }
for ($i = 0; settype($n, "int"); $i++) {}
$a = $a ? $a : 1;
$b = $b[1] ? $b[1] : 2;
$c = $c->d ? $c->d : 3;
$d = foo($x) ? foo($x) : 4;
$e = ($e) ? $e : 5;
$f = $f ?  $f : 6;
$g = $g ? /* c */ $g : 7;
$h = $h++ ? $h++ : 8;
$i = $i ?: 9;
$j = $j ? $k : 10;
$k = $a ? $a : ($b ? $b : 11);
$l = [$a ? $a : 1, $b['x'] ? $b['x'] : 2];
$m = $a === 1 ? $a === 1 : 2;
$n = $a . $b ? $a . $b : 3;
$o = -$a ? -$a : 3;
echo phpversion(), pi(), php_sapi_name(), \phpversion();
class A
{
    function f()
    {
        echo get_class(), get_class($this), get_class( ($this) ), get_class($a), get_called_class(), \get_class();
        echo GET_CLASS /* c */ ( /* d */ );
    }
}
function phpversion() {}
echo phpversion();
@trigger_error('x', E_USER_DEPRECATED);
trigger_error('x', E_USER_DEPRECATED);
\trigger_error('x', E_USER_DEPRECATED,);
trigger_error('x');
@mkdir($d);
@\unlink($f);
@unlink($f);
$a = @file_get_contents('x');
$a = @$b['x'];
