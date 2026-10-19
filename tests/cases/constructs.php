<?php
namespace  A\B;
use  Foo\Bar  as  Baz;
use  function  foo\bar;
use  const  foo\BAZ;
use Foo\{A,  B  as  C};
abstract  class  X  extends  Y  implements  I,
    J
{
    use  T1,  T2  {  T1::a  insteadof  T2;  T2::a  as  b;  }
    public  const  A = 1,
        B = 2;
    const C=1,D=2;
    final  public  static  function  f(  int  $a,  ?int  $b = null  ):  static  { return  new  static; }
    private  readonly  int  $p;
    public function __construct(public  int  $x, protected  string  $y, private  $z) {}
    abstract  protected  function  g():  int;
    public  function  h()  {
        static  $c;
        static  fn  ()  =>  1;
        static::f();
        $f = function  ()  use  ( $a ,  $b )  { return; };
        $g = fn ( $x ) :  int  =>  $x;
        return
            $a
            + $b;
    }
}
interface  I2  extends  A,
    B {}
enum  E:  int  { case  A  =  1; case  B = 2; const  X = self::A; }
trait  Tr  {}
if  ($a)  {  echo  1;  }  elseif  ($b)  {  }
else  {  }
if ($a) {
}
else {
}
if ($a) {
}
// c
else {
}
try  {  throw  new  \Exception;  }  catch  (E  $e)  {  }  finally  {  }
while  (1)  { break  1;  continue  1;  }
do  { }  while  (0);
for  (;;)  {}
foreach  ($a  as  $k  =>  $v)  {}
switch  ($a)  { case  1:  break; default: }
$x = match  ($a)  { 1  =>  2 };
$o = new  class  (1)  extends  Z  {};
$o = new  class  {};
echo  $a;  print  $a;  clone  $a;
function gen() { yield  from  bar(); yield  $a; YIELD   FROM   baz(); yield
 from x(); }
function &r() { return  1; }
#[A]  function  h1() {}
#[A]
function h2() {}
#[A, B]   class  AA {}
foo(a:  1, b:2);
include  'a.php'; require_once  'b.php';
global  $g; goto  l; l:
const  Q = 1,
  R = [
    1,
  ];
const  S = 1, T = 'a
b';
const  U = 1;
return
  1;
return  2;
/* c */  $x = 1;  /** d */  $y = 2;  // e
$z = 3;/* f */
$z = 4;/* g */;
$a = [/* h */];
abstract class Z { abstract  /* c */  function  q(); public  /* c */ $a; }
?>
text <?php  echo  1 ?> more <?=  $x ?>
