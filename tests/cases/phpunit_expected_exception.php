<?php

namespace Foo;

final class FooTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Foo::x
     * @expectedException RuntimeException
     * @expectedExceptionMessage boom
     */
    public function testX()
    {
        $this->assertTrue(true);
    }
}
