<?php

use PHPUnit_Framework_TestCase;
use \PHPUnit_Framework_Assert as A;
use PHPUnit_Util_XML;

class FooTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        PHPUnit_Framework_Assert::assertTrue(true);
        $x = new PHPUnit_Framework_Constraint_And();
        $y = PHPUnit_Util_XML::load();
        $z = \PHPUnit_Framework_TestSuite_DataProvider::class;
        $w = PHPUnit_Framework_TestCase::class . PHPUnit_Framework_Assert::X;
        $f = Foo::PHPUnit_Framework_TestCase;
        $c = PHPUnit_Framework_TestCase;
        if ($a instanceof PHPUnit_Framework_BaseTestListener) {}
        function f(PHPUnit_Framework_TestListener $l, PHPUnit_Framework_Unknown $u) {}
    }
}
class A implements PHPUnit_Framework_TestListener {}
