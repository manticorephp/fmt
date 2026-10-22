<?php
namespace Foo;

/**
 * {@inheritdoc}
 */
class A
{
    /** @inheritdoc */
    public function f() {}
    /**
     * @{inheritDoc} keep this
     * @var int $x
     */
    public $x;
    /** @var int $y */
    public static $y;
    /** @var int $z */
    static public $z;
    /** @var array{a: int, b: string} $shape */
    private readonly array $shape;
    /**
     * @var callable $cb {
     *     @var int $nested
     * }
     * @type string $t
     */
    protected $cb;
    /** @var $this $self */
    var $self;
    public function g()
    {
        $o = new class {
            /** {@inheritdoc} */
            public function h() {}
        };
        return Foo::class;
    }
}
/** @inheritdoc */
interface I {}
/** @inheritdoc */
class B extends A
{
    /** {@inheritdoc} */
    public function f() {}
}
class C
{
    use T;
    /** {@inheritdoc} */
    public function f() {}
}
/**
 * @covers Foo
 * @covers ::bar
 * @covers self::baz
 * @uses Bar\Baz
 */
final class FooTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Foo::x
     * @coversDefaultClass  Qux
     */
    public function testX() {}
}
class NotATest extends Base implements SomethingTestInterface
{
    /** @covers Zed */
    public function a() {}
}
class Plain
{
    /** @covers Zed */
    public function a() {}
}
