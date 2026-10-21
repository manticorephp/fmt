<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function testA()
    {
        foo();
    }

    /**
     * @expectedException \Foo\Bar
     * @expectedExceptionMessage Some message
     * @expectedExceptionCode 123
     * @return void
     */
    public function testB()
    {
        bar();
    }

    /** @expectedException Foo */
    public function testC() { baz(); }

    /**
     * @expectedException Foo\Bar*
     * @expectedExceptionMessageRegExp /a.*b/
     */
    protected function testD() {
    }

    /**
     * @expectedException Foo
     * @expectedExceptionMessage It's a "message" with \ backslash
     * spanning lines
     */
    public function testE()
    {
        qux();
    }

    /**
     * @expectedExceptionCode 5
     */
    public function testF() {}

    /**
     * @expectedException Foo
     * @expectedExceptionCode 7
     */
    abstract public function testG();

    /** @expectedException Foo */
    public function testH() {}
}
