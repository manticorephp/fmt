<?php
$a = 1 ;
$b = 2	;	;
$c = 3 /* c */ ;
$d = 4 // c
;
for ( ; ; ) {}
for ($i = 0;$i < 3;$i++) {}
for ($i = 0;  $i < 3;	$i++) {}
for ($i = 0; ; $i++) {}
for ( $i = 0 ;; ) {}
for (;;  ) {}
for ($i = 0;  ;  ) {}
for ($i = 0; $i < 2;  /* c */ ) {}
for ($i = 0; $i < 2;
) {}
foreach ($a as $b) { echo 1;echo 2;  echo 3; }
echo 1;/* c */echo 2;  // x
echo 3;	// y
function f() { return;}
$x = [1,2 , 3,  4];
$x = [1 , 2,3 ,
   4 ,];
$x = [ 1, 2 ];
$x = [	1, 2	];
$x = [ /* c */ 1, 2 /* d */ ];
$x = [ // c
  1,
  2, // d
];
$x = [ /* only */ ];
$x = [ ];
$x = [];
$x = array( 1 , 2,3 );
$x = array( );
$x = array( 'a' => [ 1 , 2 ] ,'b' => f( 1 , 2 ) , 'c' => function ($a , $b) { return [1 , 2]; } , );
$x = [ f($a , $b) , g(1 ,2)[1 ] , 3 ];
[ $a , $b ] = [ 1 , 2 ];
[ $a, /* c */ $b ] = $c;
[$a , $b ] = $c ;
[ $a, $b, ] = $c;
$x = [1,/* c */2, 3,// c
4, 5];
$x = [1  , /* c */ 2,  /* d */ 3,   /* e */  4 ,	5];
$y = $a [0] [1] [ 2 ] [ 3 ];
$y = $a /* c */ [0];
$y = $a // c
  [0];
$y = $a
  [0];
$y = $a[ /* c */ 0 /* d */ ];
$y = $a[ 0
];
$y = $a[
  0 ];
$y = $a ?-> b -> c;
$y = $a -> b ?-> c();
$y = $a /* c */ -> b;
$y = $a -> /* c */ b;
$y = $a
  -> b
  -> c;
$y = $a-> // c
  b;
$y = $a->	b	->c;
$y = new class implements A , B { };
$z = [new class implements A , B { } , 1 , 2];
$z = [1 , ['x' , 'y' ] , 3];
$h = [<<<EOT
  x
  EOT , 1];
