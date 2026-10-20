<?php
namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FooTest extends TestCase
{
    public function test_it_works(): void {}
    public function testAlready_ok() {}
    public function TEST_upper() {}
    /** @test */
    public function it_is_a_test() {}
    /**
     * @depends test_it_works
     * @depends App\Tests\Other::test_other_thing
     */
    public function test_with_deps() {}
    #[Test]
    public function attribute_style() {}
    #[\PHPUnit\Framework\Attributes\Test]
    public function fq_attribute_style() {}
    #[Foo, Test]
    public function two_attributes() {}
    #[Other]
    public function not_a_test() {}
    public function helper_method() {}
    public function test_http_request() {}
    public function testHttpRequest() {}
    public function test_collision() {}
    public function testCollision() {}
    public function test_URL_Parser() {}
    public function test__double__underscore() {}
    private function test_private() {}
    public function setUp(): void { $f = function () {}; }
    abstract public function test_abstract_one();
}
class NotATest { public function test_x() {} }
class Plain extends Base { public function test_y() {} }
class BarTestCase extends Base { public function test_z() {} }
class Baz implements SomeTestInterface { public function test_w() {} }
class Qux extends Base implements SomeTestInterface { public function test_v() {} }
