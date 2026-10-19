<?php
function a ( ) : int { return 1; }
function b ( ) :int { return 1; }
function c ( )   :   ?int { return 1; }
function d () /* c */ : int {}
function e () : /* c */ int {}
function f ()
    :
    int {}
function g ()/* c */: int {}
$x = fn ( ) : int => 1;
$y = function () : void {};
$z = a ? b : c;
abstract class A { abstract function h() : static; public function i():?self { } public function j() : array|null {} }
function k(? int $a, ?  Foo $b, ? \Foo\Bar $c, ? array $d, ? callable $e, ? /* c */ int $g, ?
 int $h, ?self $i, ? parent $j) {}
function l(int | string $a, A | B | null $b, A & B $c, A&B $d, (A&B) | null $e, int|string|null $f, A
    | B $g, A |
    B $h, A /* c */ | /* d */ B $i) {}
try {} catch (A | B | C $e) {} catch ( A|B $e ) {} catch (A
 | B $e) {}
#[ Foo ] class B {}
#[ Foo , Bar( 1 ) ] class C {}
#[ /* c */ Foo /* d */ ] class D {}
#[
  Foo
] class E {}
#[Foo ] function m(#[ Attr ] $a, #[A,  B ] $b) {}
function n() {}
function o()   { }
function p()
{
}
function q() {
}
function r() { /* c */ }
function s() // c
{
}
class F {}
class G
{
}
class H { function m() { } }
interface I {
}
trait T   {   }
enum En { }
enum En2: int {  }
$f = function () { };
$f = function () use ($a) { };
$o = new class { };
$o = new class
{

};
class J {

    const A = 1;

    function x() {

    }
}
class K {  // c

    const A = 1;
}
class L { /* c */

    const A = 1;
}
class M {
	
	
	const A = 1;
}
if ($a) {
}
else {
}
if ($a) {
}   elseif ($b) {
}
else    {
}
try {
}
catch (A $e) {
}
finally {
}
do {
}
while ($a);
while ($a) {
}
do { } /* c */ while ($b);
if ($a) { } // c
else { }
if ($a) { }/* c */else { }
switch ($a) {
    case 1 :
    case 2:
    case 3  ;
        break;
    case ($a ? 1 : 2) :
    case $b ?: $c :
    case 4 /* c */ :
    case 5 :  // c
    default :
        break;
}
switch ($a) {
    default:
    case 1 :
}
switch ($a) {
    default ;
    case 1 :
}
switch ($a) {
    case 1  :
    DEFAULT  :
}
switch ($a) : 
    case 1 :
    default :
endswitch;
switch ($a) {
    default /* c */ :
    case 1 :
}
