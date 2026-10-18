<?php
namespace App\Things;


use Foo\Bar;

use Foo\Baz as Q;
use function strlen;

use const PHP_EOL;
#[Attr(1)]

#[Other]
final class A extends B implements C,
    D
{

	use T1;

    use T2 { a as b; c as protected d; }
  public const X = 1,
      Y = 2;
    private int|string $p = 0;
       public readonly array $q;
    public function __construct(private int $a = 1, protected ?string $b = null) {
        parent::__construct();
    }

    public function f($x): int
      {
          if ($x) { return 1; }
          elseif ($x > 2)
          {
            return 2;
          }
          else
        return 3;
    }
    abstract protected function g(): void;
    public static function h(): static { return new static(); }

}
interface I extends J, K {
    public function m(): void;

}
enum Suit: string implements HasColor
{
    case Hearts = 'H';

    case Spades = 'S';
    const Wild = self::Spades;
    public function color(): string { return match($this) {
        self::Hearts => 'Red',
        self::Spades => 'Black',
    };
    }
}
trait T1 { public function t() {} }
$anon = new class(1) extends B { public function x() { return 1; } };
$empty = new class {};
$empty2 = new class { };
$fn = function ($a) use ($b) { return $a + $b; };
$fn2 = function () {
return 1; };
$arrow = fn($x) => $x * 2;
$obj = new #[Attr] class {};
