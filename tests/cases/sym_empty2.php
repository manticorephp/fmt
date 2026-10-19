<?php
function f1() { foo(); return; }
function f2() { foo(); return ; }
function f3() { if ($a) { return; } return; }
function f4() { if ($a) { foo(); } else { return; } }
function f5() { foo(); return; return; }
function f6() { foo(); return 1; }
function f7() { return; }
function f8() { foo(); /* c */ return; /* d */ }
function f9() { foo(); return
; }
function f10() { $f = function () { return; }; return; }
function f11() { foo(); return; }
abstract class A { abstract public function m(); public function n() { return; } }
$f = function () { foo(); return; };
$f = function () use ($a) { return; };
if ($a) { foo(); } else { return; }
function f12() { foreach ($a as $b) { return; } }
function f13() { while (foo()) return; }
function f14() { switch ($a) { case 1: return; } }
$this?->a;
$THIS?->b;
$other?->c;
$this?->a?->b;
$this -> a ?-> b;
$this
  ?->c();
while (foo()) { $a; }
while (foo());
for ($i = 0; $i < 2; $i++);
foreach ($a as $b);
do { } while ($a);
do ; while ($a);
while (foo()) { }
while (foo()) {  }
while (foo()) { /* c */ }
foreach ($a as $b) {
}
for (;;) {}
for ($i = 0; $i < 2; $i++) {}
