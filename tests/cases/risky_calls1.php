<?php

namespace Foo;

$a = $b and $c or $d;
$x = dirname(__FILE__);
$x = \dirname(__FILE__, );
$x = dirname( __FILE__ /* c */ );
$x = dirname(__FILE__, 2);
$x = dirname(dirname(__FILE__));
$s = sprintf('foo');
$s = \sprintf("foo",);
$s = sprintf('a %s', $b);
$s = sprintf(...$a);
$s = SPRINTF /* c */ ('x');
if (is_null($a)) {}
if (!is_null($a)) {}
if (!\is_null($a ?? $b)) {}
$z = (int) is_null($a);
$z = is_null($a) == true;
$z = true === is_null($a, );
$z = is_null(value: $a);
$z = is_null(foo: $a);
$z = is_null($a = 1);
$z = is_null(...);
$z = is_null();
$z = intval($a);
$z = intval($a + 1);
$z = \floatval($a)[0];
$z = doubleval($a) ** 2;
$z = strval ($a,);
$z = boolval($a, $b);
$z = strval(intval($a));
$z = strval( intval( $a ) );
function is_null($a) { return 1; }
$z = is_null($a);
$str = 'a  
b ';
$h = "x \t
y";
$e = <<<EOT
  foo   
  bar $a  
  EOT;
?>
Hello   
 World  
<?php echo 1; ?>  
tail  
