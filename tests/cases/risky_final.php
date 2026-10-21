<?php

/**
 * @internal
 */
class A {}

/**
 * @internal
 * @final
 */
class B {}

/** @Internal */
readonly class C {}

#[Internal]
class D {}

#[ORM\Entity]
/** @internal */
class E {}

/** @internal */
abstract class F {}

/** @internal */
final class G {}

class H {}

/** @covers x */
class I {}

/**
 * Text.
 * @INTERNAL(foo)
 */
// comment
class J { function f() { return new class {}; } }

#[\Foo\Internal]
class K {}

/** @internal */ /** @Entity */
class L {}
