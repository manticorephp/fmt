<?php
declare ( strict_types = 1 ) ;
declare(ticks=1) { echo 1; }
declare ( ticks = 1 ) {
}
declare /* c */ ( /* d */ strict_types=1 /* e */ ) ;
declare(
    ticks=1
);
declare( ticks=1 // c
);
//no space
//  two spaces
// ok
//	tab
///three
//
#hash
#  hash2
# hash3
#!bang
#
/*block*/
/*  block2 */
/* block3*/
/*block4 */
/**/
/***/
/** doc */
/**
 * doc
 */
/*
 multi */
/* x
*/
$a = 1; //trailing
$b = 2; /*trailing*/ // x
//¬ nbsp
//  nbsp2
// 
/*  */
/*   x   */
$x = ['a'=>1, 'b'
  => 2, 'c' =>
   3, 'd'   =>	4, 'e' // c
   => 5, 'f' => // c
   6, 'g' /* c */
   => 7, 'h' =>
   /* c */ 8, 'i'
   /* c */
   => 9];
$y = match ($a) { 1
  => 2, 3 =>
  4, default	=>	5 };
$z = fn($a)
  => $a;
use A\B as B;
use A\C as D;
use function A\f as f;
use const A\C as C;
use A\{E as E, F as G, H as H};
use A\I as I, A\J as K;
use A\L  as  L ;
use A\M/* c */as/* d */M;
use A\N as /* d */ N;
use A\O as O /* e */;
use A\P as P,A\Q as Q;
$f = function () use ($a) {};
class T { use TT { a as a; b as c; } }
