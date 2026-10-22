<?php
use Foo\Bar;

#[Bar]
#[Other]
class A {}
#[\Foo\Bar, Foo\Bar, Bar]
function f() {}
