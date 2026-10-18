<?php

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(public string $path = '/', public array $methods = ['GET'], string ...$rest) {}
}

#[Route('/a'), Other(name: 'x', value: [1, 2])]
#[ORM\Column(type: 'string', length: 10)]
class Ctl
{
    #[Route(path: '/b', methods: ['POST'])]
    public function act(#[Inject] Svc $s, #[Inject, Other] ?int $x = null): void
    {
        $r = match (true) {
            $x > 1 => 'big',
            $x === null, $x === 0 => 'none',
            default => 'small',
        };
        echo $r, PHP_EOL;
    }
}

$fn = #[Pure] fn() => 1;
$m = #[Pure] static function () { return [1, 2, 3]; };

function named(int $a, int $b = 2, int ...$more) {}
named(a: 1, b: 2);
named(1, b: 3);
named(...[1, 2]);
$x = new Foo(name: 'a', default: 'b', class: 'c', list: 1);
$y = $cond ? a : b;
$z = $cond ? f(a) : g(b);
$w = $cond ? $a : ($b ? c : d);
$t = $a ? $b : ($c ? $d : $e);
switch ($x) {
    case 1:
        break;
    case FOO:
    default:
        break;
}
label:
goto label;
