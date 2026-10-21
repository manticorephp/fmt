<?php
namespace Foo;
use DateTime;
$a = new DateTime() + DateTime::createFromFormat("Y", $s) + new \DateTime();
$b = new DateTimeImmutable();
class A extends DateTime {}
function f(DateTime $d): DateTime {}
