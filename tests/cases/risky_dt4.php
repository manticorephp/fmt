<?php
namespace Foo;
use Bar\DateTime;
$a = DateTime::createFromFormat("Y", $s) + new DateTime();
