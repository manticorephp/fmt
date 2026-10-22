<?php

NAMESPACE Foo\Bar;

USE Some\Thing AS Other;
USE FUNCTION strlen;
USE CONST FOO;

ABSTRACT CLASS A EXTENDS B IMPLEMENTS C
{
    USE T { T::foo INSTEADOF Bar; }
    PUBLIC STATIC FUNCTION f(?SELF $a, PaRent|int $b, $c = NULL): STATIC
    {
        $x = (BOOLEAN) $a + (INTEGER) $b + (Double) $c + (Binary) $d + (STRING) $e . ( ARRAY ) $f . (OBJECT)$g;
        $y = TRUE && False || Null;
        $z = SELF::FOO . STATIC::BAR . PARENT::class . Foo::NULL . $o->TRUE . $o?->False;
        $w = $a INSTANCEOF Self;
        $n = NEW STATIC();
        $m = NEW Self;
        IF ($a) { ECHO 1; } ELSEIF ($b) { RETURN; } ELSE { THROW new X(); }
        FOREACH ($a AS $k => $v) { CONTINUE; }
        $f = FN($a) => $a;
        MATCH ($a) { 1 => TRUE, DEFAULT => FALSE };
        RETURN NULL;
    }

    CONST X = TRUE;
    const NULL = 1;
    public READONLY int $p;
    FINAL PRIVATE ?Parent $q;
    function g(self $a, PARENT $b, Self ...$c) { return $a instanceof PARENT; }
    function h(Self|null $a): ?Self {}
}

function k($x) { return True\Foo . TRUE(); }
Switch ($a) {
    CASE 1;
    case 2:
        BREAK;
    case TRUE;
    DEFAULT;
        echo 1;
}
switch ($a) :
    case 1;
    default:
endswitch;
SWITCH ($a) { case $a ? 1 : 2; echo 1; }
YIELD FROM $a; EXIT(1); DIE; __halt_compiler();
Foo
