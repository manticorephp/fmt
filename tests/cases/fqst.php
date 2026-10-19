<?php
namespace Foo\Bar;

use Foo\Bar\Baz as Alias;
use Other\Thing;
use Other\Sub\{Deep, Deeper as D2};
use Exception;

/**
 * @template T of \Foo\Bar\Model
 * @param \Foo\Bar\Model $m
 * @param \Other\Thing|\Other\Sub\Deep|null $x
 * @return \Foo\Bar\Baz
 * @throws \Exception
 * @see \Other\Thing::method()
 * @see \Other\Thing::$prop
 * @var array<int, \Other\Thing>
 */
final class Impl extends \Foo\Bar\Base implements \Other\Thing, \Countable
{
    use \Foo\Bar\SomeTrait;

    /** @var \Foo\Bar\T $t */
    private \Other\Thing $thing;

    public function __construct(private readonly \Other\Thing $a, \Foo\Bar\Model ...$models)
    {
    }

    #[\Foo\Bar\Attr(1), \Other\Thing, Deep] #[\Other\Sub\Deep\X(new \Other\Thing()), \A\B\C]
    public function f(\Other\Thing $a, ?\Foo\Bar\Baz $b = null, \Other\Thing|\Foo\Bar\Model|null $c = null, (\A\B&\Other\Thing)|null $d = null): \Other\Thing|\Exception
    {
        $x = new \Other\Thing();
        $y = \Other\Thing::create();
        if ($x instanceof \Foo\Bar\Baz) {
            $z = \Foo\Bar\Model::class;
        }
        try {
        } catch (\Exception|\Other\Thing $e) {
        } catch (\RuntimeException $e) {
        }
        $fn = static fn (\Other\Thing $q): \Other\Thing => $q;
        $cl = function (\Other\Sub\Deep $d) use ($x): \Other\Sub\Deeper {
            return new \Other\Sub\Deeper();
        };
        return new \Exception();
    }
}
namespace Second;

use Foo\Bar\Model;

function g(\Foo\Bar\Model $m, \Second\Local $l): \Second\Local {}
