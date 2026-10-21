<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    public function testFoo()
    {
        $this->setExpectedException("RuntimeException", "Msg", 123);
        $this->setExpectedException("RuntimeException", null, 123);
        $this->setExpectedException("RuntimeException", "Msg");
        $this->setExpectedException("RuntimeException", null);
        $this->setExpectedException("RuntimeException");
        $this->setExpectedExceptionRegExp("RuntimeException", "/Msg.*/", 123);
        $this->setExpectedExceptionRegExp("RuntimeException", "/Msg.*/");
        $this->expectExceptionMessageRegExp('/x/');
        $this?->setExpectedException(Foo::class, 'm', 1,);
        $this->setExpectedException(
            "RuntimeException",
            "Msg",
            123
        );
        $this->setExpectedException( "RuntimeException" , "Msg" , 123 );
        $this->setExpectedException($e, foo(1, 2), $c);
        foo();
    }
}
