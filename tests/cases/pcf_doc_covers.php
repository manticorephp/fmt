<?php
/**
 * @covers Foo::bar
 * @covers Abc
 * @covers ZZ
 */
class A {}
/**
 * @covers Zed
 * @covers Alpha
 */
class B {}
/**
 * @covers Alpha
 * @covers Zed
 */
class C {}
/**
 * @covers Beta
 * @covers alpha
 * @covers \Gamma
 * @covers Alpha
 */
class D {}
/**
 * Summary.
 *
 * @covers Zed
 * @coversNothing
 * @covers Alpha
 * @covers Mid
 *   continued
 */
class E {}
/**
 * @covers Z
 * @covers 10
 * @covers 9
 * @covers 0x1
 */
class F {}
class G
{
    /**
     * @covers B
     * @covers A
     */
    public function f() {}
}
/**
 * @covers B
 * @covers A
 */
function z() {}
/** @covers B @covers A */
class H {}
