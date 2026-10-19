<?php
$a = $b.$c . $d  .  $e;
$a = 1 . 2; $a = 'x'. 1 .'y'; $a = 1 /* c */ . 2;
$a = $b /* c */ . /* d */ $c;
$a = $b// c
  . $c .// d
  $d;
$a = $b #x
 . $c;
$a = $b
    . $c
    .  $d;
$a = $b	.	$c;
$a = $b .
    $c;
$a = $b
	.$c;
$a = "x" . $b[1] . $c->d() . 0x1;
echo $a . PHP_EOL . /** d */ $b;
$a .= $b . $c; $a.='x';
