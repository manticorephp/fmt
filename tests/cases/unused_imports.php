<?php
namespace App;

use Foo\Used;
use Foo\Unused;
use Foo\InComment;
use Foo\Attr;
use function Foo\usedFn;
use function Foo\unusedFn;
use const Foo\USED_C;
use const Foo\UNUSED_C;
use App\Same;
use App\Sub\Deeper;
use Foo\Aliased as Al;
use Foo\{GroupUsed, GroupUnused};
use Foo\A1, Foo\A2, Foo\A3; // trailing
use Foo\Typed;

/** @see InComment */
#[Attr]
class X extends Used
{
    public function f(Typed $t): Al { usedFn(); return USED_C; }
    public function g(): GroupUsed { return new Deeper(); }
    public function unusedFn() {}
    const UNUSED_C = 1;
    public function h() { return $this->Unused; }
}
$a = new A2();
