<?php
class  A  extends  B  implements  C ,  D  {  }
class B extends C
implements D,
E {}
class C
{
}
class D extends E {
}
class F
extends G
{
}
class H extends I implements J,
    K,
    L
{
}
class M implements N, O, P
{
}
class Q
    implements R
{
}
class S implements
    T
{
}
abstract class U {}
final class V extends W {}
readonly final class Y {}
final readonly class Z {}
abstract readonly class AA {}
readonly abstract class AB {}
interface IA extends IB, IC {}
interface ID
    extends IE,
    IF2
{
}
trait TA {}
trait   TB   {   }
enum EA {}
enum  EB : string  implements  EC  {}
enum ED: int implements EE, EF {}
$a = new class {};
$a = new class() {};
$a = new class ( 1, 2 ) {};
$a = new class extends B {};
$a = new class(1) extends B implements C, D {};
$a = new class(
    1,
    2
) extends B {};
$a = new class(1) extends B
    implements C,
    D
{
};
$a = new class  (  $x  ,  $y  )  {  };
$a = new readonly class {};
$a = new #[Attr] class {};
$a = new class ( $x ) implements Z, Y {};
$a = new class(new class(1) {}) {};
class AC /* c */ extends /* d */ AD /* e */ implements /* f */ AE /* g */ {
}
class AF // c
{
}
class AG extends AH // c
{
}
class AI extends AJ
// c
{
}
class AK
/** doc */
extends AL {}
class AM extends AN implements AO, // c
    AP {}
#[Attr]
class AT {}
#[Attr] class AU extends AV {}
class AW extends
    AX {}
class AY implements AZ , BA ,BB {}
class BC implements BD,BE {}
final
class BF
{}
class BG extends BH implements BI
{
}
class BJ extends BK
{
    function f() {}
}
abstract class BL extends BM implements BN, BO
{
}
$o = new class extends B
{
};
$o = new class implements C
{
};
$o = new class(1)
{
};
$o = new class
{
};
$o = new class /* c */ {};
$o = new class(1) /* c */ {};
