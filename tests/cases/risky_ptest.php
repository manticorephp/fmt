<?php

namespace Foo;

class FooTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     * @depends testOther
     */
    public function itDoesThings() {}

    /** @test */
    public function single() {}

    /**
     * Text.
     * @depends other
     * @testWith [1]
     */
    public function testWithData($a) {}

    public function testNoDoc() {}

    public function helper() {}

    /** @depends testHelper */
    public function testB() {}

    /** @testdox something */
    function ownName() {}

    /**
     * @test
     * @dataProvider p
     */
    private function privateTest() {}

    public function testXyz() {}
    public function test_snake() {}
    public function testFoo_bar() {}

    /** @test @depends testA */
    public function x() {}

    /**
     */
    public function testEmpty() {}

    /** */
    public function testEmptyDoc() {}

    /** @return int */
    public function testDocSingle() {}

    /**
     * @return int
     * @depends   testB
     */
    public function multiple() {}
}
