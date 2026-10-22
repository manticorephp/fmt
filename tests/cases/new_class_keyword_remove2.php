<?php
use Baz\Qux;
echo Qux::class, Other::class, \Abs::class;
function f() { return Qux::class; }
