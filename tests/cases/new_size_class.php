<?php
namespace T;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Small;

class AaTest extends TestCase {}

/** Doc. */
class BbTest extends TestCase {}

/**
 * @small
 */
class CcTest extends TestCase {}

/**
 * @large
 */
class DdTest extends TestCase {}

abstract class EeTest extends TestCase {}

#[Small]
class FfTest extends TestCase {}

#[\PHPUnit\Framework\Attributes\Medium]
final class GgTest extends TestCase {}

/** @internal */
final class HhTest extends TestCase {
    /** @small */
    public function testIt() {}
}

class NotTest {}

#[Other]
class IiTest extends TestCase {}
