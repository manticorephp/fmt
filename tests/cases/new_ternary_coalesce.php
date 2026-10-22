<?php
$a = isset($b) ? $b : 1;
$a = isset($b['x']) ? $b['x'] : 'd';
$a = isset($b->c) ? $b->c : null;
$a = isset($b, $c) ? $b : 1;
$a = isset($this) ? $this : 1;
$a = isset($b) ? $c : 1;
$a = isset( $b ) ? $b : 1;
$a = isset($b) ?  $b   : 1;
$a = isset($b) /* c */ ? $b : 1;
$a = isset(/* c */ $b) ? /* d */ $b : 1;
$a = 1 + isset($b) ? $b : 1;
$a = !isset($b) ? $b : 1;
$a = 1 . isset($b) ? $b : 1;
$a = $q ?? isset($b) ? $b : 1;
$a = isset($b[$i++]) ? $b[$i++] : 1;
$a = isset($b[foo()]) ? $b[foo()] : 1;
$a = isset($b) ? $b : (isset($c) ? $c : 2);
$a = (isset($b) ? $b : 1);
$a = $x == isset($b) ? $b : 1;
$a = isset($b::$c) ? $b::$c : 3;
$a = isset($b) ?
    $b :
    4;
$a = [isset($b) ? $b : 1, isset($c) ? $c : 2];
$a = isset($b) ? $b : "x:y";
function gen() { $a = yield isset($b) ? $b : 1; }
$a = (int) isset($b) ? $b : 1;
