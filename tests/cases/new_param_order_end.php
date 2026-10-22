<?php
class A {
    /** @param Node[] $args  explode($delim)
     *  A codegen builtin
     *  PHP explode's overhead. */
    private function biExplode(array $args): string {}

    /** @param int $b
     *  second line */
    private function f(int $b, $a) {}

    /**
     * @param int $a
     * @param int $b second
     *  more */
    private function g($b, $a) {}
}
