<?php

namespace Foo;

final class FooTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void {}
    public function tearDown(): void {}
    private $x;

    public function testA()
    {
        $this->assertEquals(false, $b);
        $this->assertSame(true, $a);
        $this->assertNotEquals(null, $c);
        $this->assertNotSame(null, $d);
        $this->assertSame(TRUE, $a, 'msg');
        self::assertEquals( /* c */ false /* d */ , $b);
        static::assertSame(null, $a);
        $this->assertSame($a, true);
        $this->assertSame(1, $a);
        $this->assertSame(true);
        foo()->assertSame(true, $a);
        Other::assertSame(true, $a);
        $m = $this->createMock(Some::class);
        $m->method("some")->will($this->returnSelf());
        $m->method("some")->will($this->returnValue("example"));
        $m->method("some")->will($this->returnArgument(2));
        $m->method("some")->will($this->returnCallback("str_rot13"));
        $m->method("some")->will($this->returnValueMap(["a", "b", "c"]));
        $m->method("some")->WILL(self::RETURNVALUE(1,));
        $m->method("some")->will($this->returnValue(...));
        $m->method("some")->will($this->throwException(new E()));
        $m?->method("some")->will(static::returnSelf());
        $m->method("some")->will(Other::returnSelf());
    }
}

class BarTest extends TestCase
{
    protected function setUp(): void {}
    private function tearDown() {}
    function setup() {}
}

abstract class BazTest extends Base
{
    function setUp() { function inner() {} }
    static function tearDown() {}
    public static function tearDown2() {}
    final public function SETUP() {}
}

class Plain
{
    public function setUp() {}
}
