<?php
class A
{
    public function __construct(private int $a, protected string $b, public $c) {}

    public function foo(int $x, $y) {}

    public function __invoke($a, $b = [1, 2], ...$rest) {}
}
class B
{
    public function __construct(
        private int $a,
        protected string $b
    ) {}
}
class C
{
    public function __construct(private int $a) {}
}
class D
{
    public function __construct(
        private int $a,

        protected string $b,
    ) {}
}
class E
{
    public function __construct(
        private int $a
    ) {}
    public function plain(
        $a,
        $b
    ) {}
}
class F
{
    public function __construct(/* c */ private int $a, #[Attr] protected string $b) {}
}
class G
{
    public function __construct(
        // comment
        private int $a,
    ) {}
}
class H
{
    public function __construct(private array $a = [1, 2, 3], private ?Foo $f = null, callable $cb = null) {}
    public function other(private_not $x) {}
}
$o = new class(1) { public function __construct(private int $a, public int $b) {} };
interface I { public function __construct(int $a); }
abstract class J { abstract public function __construct(int $a, int $b); }
class K { public function __construct(public readonly int $a, public readonly string $b = 'x'){} }
