<?php
require 'a.php';
require   'a.php'  ;
require('a.php');
require ('a.php') ;
require( 'a.php' );
require(  'a.php'  )  ;
require_once(__DIR__ . '/x.php');
include_once  (  $path  )  ;
include$path;
include $path ;
include /* c */ 'x' ;
include 'x' /* c */ ;
include 'x' ; /* c */
include 'x' // c
;
include('x') /* c */;
include( /* c */ 'x' /* d */ );
include
    'x'
    ;
include (
    'x'
);
$a = include('x');
$a = include ('x') ?: 1;
if ((include 'x') === 1) {}
foo(include ('x'));
foo(include('x'), 2);
$b = [include('x')];
$c = (include('x'));
require ('a.php').'b';
require ('a.php') . 'b';
require(('a.php')) ;
include('x')
?>
<?php require ('y') ?>
<?php require ( 'y' ) ?>
