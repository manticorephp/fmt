<?php
namespace Foo\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes as PU;
use PHPUnit\Framework\TestCase;

class ATest extends TestCase
{
    public function testOne(): void {}
}

/**
 * Existing doc.
 */
final class BTest extends TestCase
{
}

/** One liner */
class CTest extends TestCase
{
}

/**
 * @covers \Foo\Bar
 */
class DTest extends TestCase
{
}

/**
 * @internal
 */
class ETest extends TestCase
{
}

abstract class FTest extends TestCase
{
}

#[CoversClass(Foo::class)]
class GTest extends TestCase
{
}

#[PU\CoversNothing]
final class HTest extends TestCase
{
}

#[\PHPUnit\Framework\Attributes\Small]
class ITest extends TestCase
{
}

/**
 * Doc.
 */
#[Attr]
class JTest extends TestCase
{
}

// a comment
class KTest extends TestCase
{
}

    class LTest extends TestCase
    {
    }

readonly class MTest extends TestCase
{
}

$x = new class extends TestCase {
};

class NotATest
{
}

class OtherThing extends Base
{
}

class PTest extends Base implements SomeTestInterface
{
}

/**
 * @coversDefaultClass \Foo
 */
class QTest extends TestCase
{
}

/**
 * @coversNothing
 */
class RTest extends TestCase
{
}

/** @covers Foo */
class STest extends TestCase
{
}

/**
 * Summary.
 *
 * @author me
 */
final class TTest extends TestCase
{
}
