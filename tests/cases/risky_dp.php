<?php

namespace Foo;

use PHPUnit\Framework\Attributes\DataProvider;

class FooTest extends \PHPUnit\Framework\TestCase
{
    /** @dataProvider dataProvider */
    public function testSomething($expected, $actual) {}
    public function dataProvider() { return []; }

    /**
     * @dataProvider provideA
     * @dataProvider provideB
     */
    public function testTwo() {}
    public function provideA(): array { return []; }
    public function provideB(): ?array { return []; }

    #[DataProvider('attrProvider')]
    public function test_feature_x() {}
    public static function attrProvider(): iterable { yield [1]; }

    /** @dataProvider shared */
    public function testShared1() {}
    /** @dataProvider shared */
    public function testShared2() {}
    public function shared() { return $this->x(); }

    /** @dataProvider provideExistingCases */
    public function testExisting() {}
    public function dp2() {}
    public function provideExistingCases() {}

    /** @dataProvider self::other */
    public function testOther() {}
    protected function other(): \Generator { yield 1; }

    #[DataProvider('inlineProvider')]
    #[\PHPUnit\Framework\Attributes\DataProvider('second')]
    public function testMulti() {}
    function inlineProvider(): Iterable {}
    function second(): iterable {}

    /** @dataProvider noReturn */
    public function testNoReturn() {}
    private function noReturn() { return $this->data; }

    /** @dataProvider forced */
    public function testForced() {}
    public function forced() { return self::x(); }

    /** @dataProvider thisUse */
    public function testThisUse() {}
    public function thisUse(): array|null { return $this->d; }
    public function thisUse2(int $a): static {}
    public function useLambda() {}
}
