<?php

$a = DateTime::createFromFormat('Y-m-d', $s);
$a = \DateTimeImmutable::createFromFormat("H:i", $s);
$a = DateTime::createFromFormat('!Y-m-d', $s);
$a = DateTime::createFromFormat('Y', $s, $tz);
$a = DateTime::createFromFormat($f, $s);
$a = DateTime::createFromFormat('Y' . 'x', $s);
$a = datetime::CREATEFROMFORMAT(b'Y-m-d', $s);
$a = Foo\DateTime::createFromFormat('Y-m-d', $s);
$a = DateTime::createFromFormat('Y', $s)->x();
$a = new DateTime();
$a = new \DateTime('now') + date_create() + date_create_from_format('Y', $s) + \date_create() + DateTime::createFromFormat('Y', $s);
$a = DateTime::class . DateTime::ATOM . $x->DateTime . DateTime::foo() . DateTime::$x . Foo::DateTime;
function DateTime() {}
$d = new \DateTime;
printf('bar');
printf("a %s");
printf('a', $b);
\printf('bar',);
printf(...$a);
printf(...);
$x->printf('a');
echo 1; printf('bar') ?>
