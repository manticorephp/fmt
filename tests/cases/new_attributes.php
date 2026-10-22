<?php
namespace T;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Foo\Bar
 * @group slow
 * @small
 * @coversNothing
 * @backupGlobals enabled
 * @backupStaticAttributes disabled
 * @preserveGlobalState enabled
 * @runTestsInSeparateProcesses
 * @uses \Foo\Baz
 * @testdox Something
 * @ticket 123
 */
final class AaTest extends TestCase
{
    /**
     * @test
     * @dataProvider provider
     * @dataProvider \Other\Provider::dp
     * @depends testOne
     * @depends clone testTwo
     * @depends shallowClone testThree
     * @depends Foo::testFour
     * @depends Foo::class
     * @depends clone Foo::class
     * @requires PHP 8.1
     * @requires PHP >= 8.0
     * @requires PHPUnit 10
     * @requires extension json
     * @requires function foo
     * @requires function Foo::bar
     * @requires OS Linux
     * @requires OSFAMILY Windows
     * @requires setting display_errors 1
     * @group fast
     * @doesNotPerformAssertions
     * @before
     * @after
     * @beforeClass
     * @afterClass
     * @runInSeparateProcess
     * @preCondition
     * @postCondition
     * @ticket ABC-1
     * @testdox it does
     */
    public function testOne() {}

    /**
     * @testWith [1, 2]
     *           ["a", "b"]
     * @testWith [3, 4]
     * @covers \Foo\Bar::baz
     * @covers ::strlen
     * @covers Foo
     * @covers FooTrait
     * @covers \Foo::
     * @uses ::bar
     * @uses Baz
     * @uses \A\B
     */
    public function testTwo() {}

    /** @test */
    public function simple() {}

    /**
     * @dataProvider provider()
     */
    #[Test]
    public function withAttr() {}

    #[\PHPUnit\Framework\Attributes\Test]
    /** @test */
    public function dup() {}

    /**
     * Some description.
     *
     * @param int $x
     * @test
     * @return void
     */
    public function mixed($x) {}

    /** @test @dataProvider x */
    public function oneLine() {}

    /**
     * @requires PHP
     * @depends
     * @group
     * @testWith
     * @requires unknown thing
     */
    public function broken() {}

    /**
     * @dataProvider it's
     * @testdox it's a test
     */
    public function quotes() {}
}
