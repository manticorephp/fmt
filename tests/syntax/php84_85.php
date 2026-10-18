<?php
class Hooks
{
    public string $a { get => $this->a; set(string $v) { $this->a = strtolower($v); } }
    public int $b { get { return 1; } }
    public private(set) int $c = 0;
    protected(set) string $d = '';
    public public(set) readonly int $e;
    public string $f { #[Deprecated] get => 'f'; set => $value; }
    abstract public string $g { get; set; }
    public function __construct(public private(set) int $h = 1, public protected(set) ?string $i = null) {}
}
interface HI { public string $name { get; } }
$r = $x |> trim(...) |> strtoupper(...);
$s = 'a' |> fn($v) => $v . 'b';
$o = new Hooks();
$c = clone $o;
function f(): void { (void) foo(); }
$d = new Foo()->bar();
$e = (new Foo)->bar();
static fn() => 1;
$cl = static function (): static { return new static; };
$arr = ['a' => 1, ...$other];
$x = $a <=> $b;
$x = $a ** 2;
$x **= 3;
$x ??= 4;
$n = 1_000_000 + 0b1010 + 0o17 + 0x1F + 1.5e3;
final class FC { final public function m() {} }
enum E: int { case A = 1; public const C = self::A; }
echo E::A->value, E::from(1)->name, E::A::class;
function gen() { yield 1; yield from [2]; $x = yield; }
echo `ls`;
echo __LINE__, __FILE__, __DIR__, __FUNCTION__, __CLASS__, __METHOD__, __NAMESPACE__, __TRAIT__, __PROPERTY__;
