<?php
interface I { public function f(): static; const X = 1; }
trait Tr { abstract public function g(int|string $a): void; public static function h(): never { exit; } }
abstract class AC implements I
{
    abstract public function g2((A&B)|null $a, A|(B&C) $b): (X&Y)|Z;
    public function f(): static { return $this; }
    public function mix(int|string|null $a, A&B $b, &$ref, &...$refs): void {}
    public function amp(array &$a, $b = A | B, $c = A & B, $d = 1 & 2) {}
}
try {} catch (A | B $e) {} catch (E) {}
$x = $a | $b;
$y = $a & $b;
$z = $a && $b || $c;
$w = ($a & $b) | ($c & $d);
$v = fn(int|string $x, A&B $y): int|float => $x;
const A = 1;
const B = A | 2, C = A & 3;
$q = $a ?? $b ?: $c;
$u = $a ? ($b ? 1 : 2) : 3;
$p = $a ? $b ? 1 : 2 : 3;
$o = (int) $x + (float) $y . (string) $z . (bool) $b . (array) $c . (object) $d;
if ($x instanceof static) {}
$r = $x instanceof self ? 1 : 2;
$t = static::$prop ?? static fn() => 1;
class WithStatic { public static ?int $n; public ?int $m; protected ?string $s = null; var ?int $v; private static ?self $i; }
function ret(): ?int { return 1; }
function ret2(): ?\Foo\Bar { return null; }
$fn = function (?int $a, ?string $b = null) use ($x): ?int { return $a; };
echo $a ? 'x' : 'y';
echo $a ?: 'y';
$a = [ 'k' => fn(): int => 1, ];
$res = $obj->method(a: 1)->other(b: [1, 2], c: fn(int $x): int => $x);
