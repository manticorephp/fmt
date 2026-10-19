<?php
namespace Foo\Bar {
    class A {}
    { echo 1; }
    { { echo 2; } }
    if ($a) { { echo 3; } }
    switch ($a) { case 1: { echo 4; } break; default: { echo 5; } }
    function f() {} { echo 6; }
    ; { echo 7; }
}
