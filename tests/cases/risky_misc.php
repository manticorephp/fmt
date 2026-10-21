<?php

namespace Foo;

if ($a == $b || $c != $d || $e <> $f || $a === $b || $a !== $b) {}
$x = $a == 1 ? 1 : 2;
$a = array_keys($b) + array_keys($b, 1) + array_keys($b, 1, false) + array_keys($b, 1,) + \array_keys( $b , 1 );
$a = array_search($n, $h) + in_array($n, $h) + in_array($n, $h, true) + base64_decode($s) + base64_decode($s, true) + base64_decode($s,);
$a = mb_detect_encoding($s) + mb_detect_encoding($s, 'UTF-8') + mb_detect_encoding($s, 'UTF-8', false) + mb_detect_encoding($s, ['a', 'b']);
$a = in_array(foo(1, 2), [1, 2, 3]) + in_array($x, array(1, 2));
$a = in_array(...$args) + $x->in_array($a, $b) + new in_array($x);
class FooTest extends \PHPUnit\Framework\TestCase
{
    public function testA()
    {
        $this->assertEquals(1, $a);
        $this->assertNotEquals(1, $a, 'msg');
        $this->assertEquals(1);
        $this->assertEquals(1, 2, 3, 4);
        self::assertAttributeEquals(1, 'p', $o);
        static::assertAttributeNotEquals(1, 'p', $o, 'm');
        $x->assertEquals(1, 2);
        $this->assertSame(1, 2);
    }
}
unset($this->a);
unset($this->a, $b);
unset($a, $this->b);
unset($a, $this->b, $c);
unset($a, $b);
unset($this->a, $this->b,);
unset(Foo::$bar, $x->y->z, \Foo\Bar::$baz, $this->a['x'], $a);
unset( /* c */ $this->a /* d */ , $b);
unset(Foo :: $a, $b);
unset($a->b(), $c->d);
function g() {
    yield from [1, 2, 3];
    yield from [1, [2, 3], foo(4, 5),];
    yield from array(1 => 'a', 2 => 'b');
    yield from [];
    yield from [yield from [1]];
    $x = yield from [1, 2];
    yield from foo();
    yield from [
        'a',
        'b', // c
    ];
    if ($a) { yield from [1,]; }
}
