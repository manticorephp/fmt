<?php
class A
{
    public $a;
    use T1;

    public function f() {}

    use T2, T3; // c
    const X = 1;
    #[Attr]
    private static function g() {}
    use T4 { x as y; }
    protected function __construct() {}
    public function setUp(): void {}
    public function __toString(): string { return ""; }

}
