<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    public function testA()
    {
        $this->assertTrue(is_float( $a), "my message");
        $this->assertTrue(is_nan($a));
        $this->assertFalse(is_nan($a));
        $this->assertTrue(\is_null($a, ));
        $this->assertFalse(is_null($a));
        $this->assertTrue(array_key_exists('a', $b));
        $this->assertFalse(array_key_exists('a', $b), 'm');
        $this->assertTrue(array_key_exists('a'));
        $this->assertTrue(empty($a));
        $this->assertFalse(empty($a['x']));
        $this->assertTrue(file_exists($f));
        $this->assertFalse(is_dir($f));
        $this->assertTrue(is_readable($f));
        $this->assertFalse(is_writable($f));
        $this->assertTrue(is_infinite($a));
        $this->assertFalse(is_infinite($a));
        $this->assertTrue(str_contains($a, $b));
        $this->assertFalse(str_ends_with($a, $b), 'msg');
        $this->assertTrue(str_starts_with( $a , 'x' ));
        $this->assertTrue(is_string($a));
        $this->assertFalse(is_int($a, ));
        $this->assertTrue(is_boolean($a));
        $this->assertTrue(is_scalar($a), 'x');
        $this->assertTrue(is_array($a, $b));
        $this->assertTrue($a instanceof Foo);
        $this->assertFalse($a instanceof \Foo\Bar, 'm');
        $this->assertTrue(!$a instanceof Foo);
        $this->assertFalse(!$a instanceof $b);
        $this->assertTrue($a instanceof Foo && $b);
        $this->assertTrue($a);
        $this->assertTrue(foo($a));
        $this->assertTrue(\is_string($a));
        $this->assertSame(1, count($a));
        $this->assertNotSame($b, \count($a), 'x');
        $this->assertEquals(2, sizeof($a));
        $this->assertNotEquals(3, count($a, 1), 'x');
        $this->assertSame($e, count($a) + 1);
        $this->assertSame(count($a), 1);
        $this->assertSame([], count($a));
        $x->assertTrue(is_null($a));
        $this->assertInternalType("array", $var);
        $this->assertNotInternalType('null', $var);
        $this->assertInternalType('boolean', $var, 'x');
        $this->assertInternalType('unknown', $var);
        $this->assertInternalType($t, $var);
        $m = new class { function f() { $this->assertInternalType('string', $a); } };
    }
}
