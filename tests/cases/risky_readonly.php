<?php

/**
 * @readonly
 */
class A {}

/** @readonly */
final class B {}

/**
 * Text.
 *
 * @readonly
 * @internal
 */
abstract class C {}

/** @readonly */
readonly class D {}

/** @readonly */
function f() {}

/** @readonly */
class_alias('a', 'b');

/**
 * @readonly
 * @readonly
 */
#[Attr]
class E {}

/** @readonly */ // comment
class F {}

/** @readonly */
interface G {}

class H {
    /** @readonly */
    public int $x;
}
