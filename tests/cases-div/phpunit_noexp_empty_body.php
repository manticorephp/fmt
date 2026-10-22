<?php

// Known divergence (outside the parity matrix): with a set that runs php_unit_no_expectation_annotation BEFORE another AbstractPhpUnitFixer rule (@PHPUnit5x7Migration:risky ...),
// upstream throws "Missing block "end"" (stale block cache) when the annotation sat on a method with a one-line empty body followed by another method: the file stays as it is.
class FooTest extends \PHPUnit\Framework\TestCase
{
    /** @expectedException Foo */
    public function testH() {}
    public function testI() {}
}
