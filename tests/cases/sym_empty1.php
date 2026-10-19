<?php
namespace Foo;
;
$a = 1;;
{ ; }
if ($a) { ; }
if ($a) { foo(); };
if ($a) { foo(); } else { bar(); };
if ($a) { foo(); } elseif ($b) { bar(); };
try { foo(); } catch (\Exception $e) { bar(); } finally { baz(); };
try { foo(); } catch (\Exception $e) { bar(); };
for ($i = 0; $i < 3; $i++) { foo(); };
for (;;) { break 1; };
foreach ($a as $b) { continue 1; };
while ($a) { while ($b) { break 2; }; };
switch ($a) { case 1: break; };
do { foo(); } while ($a);;
function f() { return 1; };
$g = function () { return 1; };
$h = fn() => 1;;
class A { public function m() {} public $x; };
interface I { function f(); };
trait T { };
enum E { case A; };
$o = new class { };
$o = new class extends A implements I, J { };
$p = new class(1) extends A { };;
1;
'str';
$a;
foo;
2.5;
$a ;
$b
;
{ $a; }
{ foo(); ; bar(); }
{ ; ; }
foo(); ; /* c */ ;
function g() { ; }
if ($a): ; endif;
switch ($a) { case 1: ; break; }
for ($i = 0; ; $i++) ;
for ($i = 0;; $i++) {}
for (;;) ;
class B { public int $p { get { return 1; } set { ; } } }
