<?php

namespace Foo;

use Bar\Baz;

const INT = 0Xff;
const B = 0B1010_1010 + 0O17 + 0xabc + 0XDEF + 0b1 + 017 + 1_000;

echo __LINE__, __Line__, __file__, __DIR__, __Function__, __CLASS__, __method__, __NAMESPACE__, __trait__;
echo Baz::CLASS, Baz::Class, static::CLASS, $x::Class, Baz::class;

class Magic
{
    public function __Construct(public INT $a = 0, ?String $b = null, Float|BOOL|NULL $c = null) {}
    public function __TOSTRING(): STRING { return ''; }
    public function __CALLSTATIC($n, $a) {}
    public function __Set_State($a) {}
    public function __DEBUGINFO(): ARRAY { return []; }
    public function __Invoke(...$a): VOID {}
    public function &__GET($n) {}
    public function f(SELF $x, Parent $p, callable $c, CALLABLE $cc, ITERABLE $i, Object $o, MIXED $m): STATIC|FALSE|null {}
    public function g(): ?SELF { return $this->__INVOKE(1) ?: $this?->__ToString() ?: static::__CallStatic(1) ?: self::__SET_STATE(2) ?: self::__Get(3); }
    const INT FOO = 1;
    public Int|String $prop;
    public function __tostring2() { $o->__WAKEUP(); Foo::__Clone(); __DESTRUCT(); }
    public function h(INT $int = Int, $x = NULL, $y = STRING) { return STRING; }
    public function i(): NEVER {}
    public function j(): TRUE {}
    public function k(array|ARRAY $a): void
    {
        $f = function (INT $a): STRING { return $this->__SLEEP(); };
        $g = fn(Int $a): Bool => $a;
        function __WAKEUP() {}
        new class { public function __ISSET($n) {} };
    }
}

interface I { public function __SLEEP(); public function x(INT $a): VOID; }
trait T { public function __UNSET($n) {} }
enum E: STRING { case A = 'a'; const X = INT; public function __invoke() {} }

function __Get() {}
function f(Int $INT, String $String) { return \INT\f() + Int::A + Foo\INT; }
