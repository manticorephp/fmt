<?php
echo $$foo;
echo $$foo['bar'];
echo $foo->$bar['baz'];
echo $foo->$callback($baz);
echo $foo?->$bar;
echo $$$foo;
echo $ $foo;
echo $foo->  $bar;
echo $foo->/* c */$bar;
echo ${$foo};
echo $foo->{$bar};
echo $foo::$bar;
echo "$$foo";
echo "{$foo->$bar}";
class A { public function f() { return $this->$name . $this::$x . static::$$y; } }
$x = new $foo;
$x = new $foo->bar;
echo $foo->$bar->$baz;
echo $a->b->$c;
