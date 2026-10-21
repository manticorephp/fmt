<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    public function testA()
    {
        $this->assertSame(1, 2);
        self::assertSame(1, 2);
        static::assertSame(1, 2);
        static::assertTrue(false);
        $this->createStub(Foo::class);
        $m->method('x')->will($this->returnValue(1));
        $this->any();
        $x->assertSame(1, 2);
        Other::assertSame(1, 2);
        parent::assertSame(1, 2);
        $this->assertSame(...);
        $this->assertSame;
        $f = function () { $this->assertTrue(1); };
        $g = fn () => $this->assertTrue(1);
        $o = new class { function f() { $this->assertTrue(1); } };
        $this?->assertSame(1, 2);
    }

    public static function dp()
    {
        static::assertTrue(1);
        $this->assertTrue(2);
        self::fail('x');
    }

    public function testB()
    {
        $this->expectException(Foo::class);
        $this->setUp();
        $this->markTestSkipped();
        static::setUpBeforeClass();
    }
}
