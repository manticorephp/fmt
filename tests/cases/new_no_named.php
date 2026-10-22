<?php
namespace N;

use Attribute;

class A {}

/** Doc. */
class B {}

/**
 * Doc.
 *
 * @internal
 */
final class C {}

/**
 * @no-named-arguments
 */
class D {}

/** @no-named-arguments with text */
class E {}

#[Attribute]
class F {}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class G {}

interface H {}

trait I {}

enum J {}

abstract class K {}

readonly class L {}

$x = new class {};

/**
 * @internal
 */
class M {}

#[Foo]
/** after attr */
class O {}

#[Foo]
class P {}
