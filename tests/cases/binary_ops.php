<?php
declare(strict_types=1);
declare(ticks =1);
namespace A;
use function foo\bar;
const X=1+2;
enum E: int { case A=1; case B  =  2; }
function &f(int|string $a=1, (A&B)|null $b=null, &$c=[1=>2, "k"=>3]): ?int { return $a  +$b-$c*  2; }
$a=1;$b  =  2;$c	=	3;
$d =$a/* c */+$b;
$e = $a +/* c */ $b;
$f = $a // c
  + $b;
$g = $a
  + $b
  ?? $c;
$h = $a<=>$b; $i = $a**$b; $j=$a<<1|$b>>2^3&4;
$k??=5; $k.= 'x'; $k.='y'; $l = $a.$b;
$m = $a and $b or $c xor $d; $n = $a AND $b;
$o = $a!=$b; $p = $a<>$b; $q = $a!==$b; $q2 = ($a===$c)==$d;
$r = -$a + +$b - -$c & $d;
$s = [&$a, 1 =>2];
$t = fn($x)=>$x*2;
$u = match($a) { 1=>2, default=>3 };
$v = $a ?: $b; $w = $a ? $b:$c; $z=$a&&$b||$c;
foreach ($a as $k=>$v) { $x=&$y; $x&=3; $x|=1; $x^=2; $x%=3; $x*=2; $x/=2; $x-=1; $x+=1; $x<<=1; $x>>=1; $x**=2; }
$s = "a=b $a[-1] {$a} x+y";
echo $a    <  $b,  $a>$b, $a>=$b, $a<=$b;
function g(callable $c = null, int ...$r) { return $c(...$r) &$a; }
$h = <<<EOT
a = b
EOT;
?>
a=b+c <?= $a+$b ?>
