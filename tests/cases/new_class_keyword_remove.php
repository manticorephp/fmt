<?php
namespace Foo\Bar;

use Baz\Qux;
use Baz\Quux as Alias;
use Other\{One, Two as Three};
use function Fn\func;
use const Co\CONSTANT;

echo Qux::class;
echo Alias::class;
echo One::class;
echo Three::class;
echo Plain::class;
echo \Absolute\Name::class;
echo Sub\Name::class;
echo Qux\Sub::class;
echo Alias\Sub::class;
echo self::class, static::class, parent::class;
echo $obj::class;
echo namespace\Rel::class;
echo Qux
    ::class;
echo /* c */ Qux::class;
$x = [Qux::class => Plain::class];
class K { public function f() { return Plain::class; } const C = Qux::class; }

namespace Second;

use A\B;
echo B::class;
echo C::class;
