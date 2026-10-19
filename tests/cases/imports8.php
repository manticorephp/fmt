<?php
class X { use T; use U { a as b; } function f() { $f = function() use ($a) {}; } }
use G;
