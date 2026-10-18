<?php
declare(strict_types=1);

namespace App\Model;

use Foo\{Bar, Baz as Q};
use function Foo\{fn_a, fn_b};
use const Foo\{C_A, C_B};
use function strlen;
use const PHP_EOL;

enum Suit: string implements \JsonSerializable
{
    use T1;
    case Hearts = 'H';
    case Spades = 'S';
    const Wild = self::Spades;

    public static function fromChar(string $c): self
    {
        return self::from($c);
    }

    public function label(): string
    {
        return match ($this) {
            self::Hearts => 'Hearts',
            self::Spades, self::Wild => 'Spades',
            default => throw new \LogicException(),
        };
    }
}

enum Plain { case A; case B; }
enum IntBacked: int { case One = 1; }

final readonly class Point
{
    public function __construct(
        public int $x = 0,
        protected ?int $y = null,
        private readonly int|string $z = 1,
        #[\SensitiveParameter] public ?\Closure $cb = null,
        public \Countable&\Traversable $both = new \ArrayObject(),
    ) {
    }

    public function with(int ...$xs): static
    {
        return new static(...$xs);
    }
}

class Trait1
{
    use A, B { A::foo insteadof B; B::foo as protected bar; }

    final public const string NAME = 'x';
    public static ?self $inst = null;
    public ?int $n = null;
    abstract protected function f(?int $a, ?string ...$rest): ?static;
}

$obj = new class(1, 2) extends Point implements \Countable {
    use SomeTrait;
    public function count(): int { return 0; }
};
$f = static fn(int $a): int => $a * 2;
$g = function () use ($f, &$obj): ?int { return null; };
$h = fn&(array $a): array => $a;
function &refRet(array &$a): array { return $a; }
function nul(null|int $a, int|null $b, ?int $c = null): int|false|null { return 1; }
