<?php
function  foo  ( $a , $b )   {  return 1; }
function bar():int{return 1;}
function baz ()
{
}
function qux($a,
    $b
)
{
}
function ( $x ) { };
$f = function( $x )use( $y ){ };
$f = function ( $x ) use ( $y ) { };
$f = function () use ($a,
    $b) {};
$f = function () use
    ($a) {};
$f = function ()
    use ($a)
{
};
$f = function &( $x ) { };
$f = function  &  ($x) {};
$g = fn( $x )=>$x;
$g = fn  ( $x )  =>  $x;
$g = fn ( $x ) /* c */ => $x;
$g = fn &( $x ) => $x;
$h = static function () {};
$h = static   function () {};
$h = static /* c */ function () {};
$h = static
  function () {};
$h = static fn() => 1;
$h = static  fn () => 1;
abstract class A {
    abstract public function  m ( $a ) ;
    public static function  n  ( $a )
    {
    }
    function o( /* c */ $a /* d */ ) {}
    function p(  ) {}
    function q( ) /* c */ {}
    function r() /* c */
    {}
    function s() // c
    {}
    function &t ( ) {}
    function u(
        $a,
        $b
    ): int {}
    function v( ) : ?int {}
}
interface I { function f( $a ); }
function w() /* c */ : int {}
function x($a) : int|null    {  }
