<?php
class X {
    public function a() { // comment after brace
        return 1;
    }
    public function b() /* c */ {
        return 2;
    }
    public function c()
    // between
    {
        return 3;
    }
    public function d() { # hash
    }
}
function e() {    // trailing
    // first
        // second
    return 1;
}
    // dangling
if ($x) {
    foo();
        // comment before else
} else {
    bar();
}
if ($y) {
    foo();
    // comment before elseif
} elseif ($z) {
    bar();
}
switch ($a) {
    // before case
    case 1:
        // in case
        break;
}
$fn = function () { // c
};
