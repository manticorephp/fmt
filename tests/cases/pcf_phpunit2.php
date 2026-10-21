<?php
class A1Test extends TestCase
{
    public function testA() {}
    /**
     * @dataProvider provideA
     */
    public function testB($x) {}
    public static function provideA() { return []; }
    public function testC() {}
}
class B1Test extends TestCase
{
    public static function provideA() { return []; }
    public function testA() {}
    /** @dataProvider provideA */
    public function testB($x) {}
    /**
     * @dataProvider provideB
     * @dataProvider provideC
     */
    public function testC($x) {}
    public static function provideB() { return []; }
    public static function provideC() { return []; }
    public function testD() {}
}
class C1Test extends TestCase
{
    public function testA() {}
    public function provideA() { return []; }
    /**
     * @dataProvider provideA
     */
    public function testB($x) {}
    /**
     * @dataProvider provideA
     */
    public function testC($x) {}
    public function provideB() { return []; }
    public function testD() {}
}
class D1Test extends TestCase
{
    public function testB($x) {}
    #[DataProvider('provideX')]
    public function testA($x) {}
    public function provideY() {}
    public function provideX() {}
    #[PHPUnit\Framework\Attributes\DataProvider('provideY')]
    public function testC($x) {}
}
class E1Test extends TestCase
{
    private $a;
    const X = 1;
    public function setUp(): void {}
    public function testA() {}
    /**
     * @dataProvider provideA
     */
    public function testB($x) {}
    // comment
    public function testC() {} // trailing
    public static function provideA() { return []; }   // trailing two
    /** doc */
    protected $z = 1;
}
class F1Test extends TestCase
{
    /**
     * @dataProvider providerMissing
     */
    public function testB($x) {}
    /**
     * @dataProvider Foo::provideA
     */
    public function testC($x) {}
    public static function provideA() { return []; }
}
class G1Test extends TestCase
{
    public function provideA() { return []; }
    /**
     * @dataProvider provideA
     * @dataProvider provideB
     */
    public function testC($x) {}
    public function provideB() { return []; }
    /**
     * @dataProvider provideB
     */
    public function testD($x) {}
    /**
     * @dataProvider providea
     */
    public function testE($x) {}
}
class H1Test extends TestCase
{
    public function provideC() {}
    public function provideB() {}
    public function provideA() {}
    /** @dataProvider provideA */
    public function testA() {}
    /** @dataProvider provideB */
    public function testB() {}
    /** @dataProvider provideC */
    public function testC() {}
}
