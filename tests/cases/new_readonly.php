<?php
readonly class A {
    public readonly int $a;
    readonly public string $b;
    private readonly ?array $c;
    public function __construct(
        public readonly int $d,
        public readonly \Foo\Bar&Baz $g,
        public readonly (A&B)|null $h,
        protected readonly string $e = 'x',
        readonly private array|int $f = [],
    ) {}
    public readonly int $i;
    #[Attr] public readonly int $j;
    public int $k;
}
final readonly class B { public readonly int $z; }
abstract readonly class C { public readonly int $z; }
class D { public readonly int $z; public function __construct(public readonly int $y) {} }
$x = new readonly class { public readonly int $q; };
readonly class E { public readonly /* c */ int $w; protected readonly
  int $v; }
