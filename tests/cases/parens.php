<?php
declare ( ticks = 1 , strict_types = 1 ) ;
declare(ticks /* c */ = /* d */ 1);
declare(ticks // c
  =
  1);
declare(ticks=
 1);
Foo :: bar ( ) ; Foo /* c */ :: /* d */ bar();
$a = Foo\Bar :: class . self :: X . static
  :: Y . $x
  :: z() . $x ::
  $y;
function foo ( $a , $b ) { return strlen ( $a ) . foo ($b) . $f (1) . $f [0] (2) . $o->m (3) . (function () {}) (4); }
function & bar ( ) {}
$x = new Foo ( 1 ) ; $y = new Foo ( ) ; $z = new class ( 1 ) {};
isset ( $a ) ; unset ( $a ) ; empty ( $a ) ; exit ( 1 ) ; list ( $a , $b ) = [ 1 , 2 ] ; array ( 1 , 2 ) ; $q = array ( ) ;
echo ( 1 ) ; echo ( 1 ) . 'a' ; print ( 1 ) . 'b' ; include ( 'x' ) ; require_once ( 'x' ) . 'y';
if ( $a ) { } while ( $a ) { } foreach ( $a as $b ) { } switch ( $a ) { }
foo( /* c */ $a /* d */ );
foo(
    $a
);
foo( $a, );
foo( $a /* c */ , );
foo(  // c
    $a );
foo( $a // c
);
foo(( $a ));
$f = fn ( $x ) => ( $x ) ;
$f = function ( $x ) use ( $y ) { };
$g = ( int ) $x;
try { } catch ( Exception $e ) { }
match ( $a ) { 1 => ( 2 ) };
$x = new ( $cls ) ( 1 );
$x = ( new Foo ( 1 ) ) -> bar ( );
$a = [ ( 1 ) , ( 2 ) ];
foo ( ) ( ) ( 1 );
$a = $b [ 0 ] ( ) ;
function f ( ( A&B ) | null $x ) {}
